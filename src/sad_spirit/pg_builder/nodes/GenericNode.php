<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    Node,
    Parser,
    exceptions\InvalidArgumentException
};

/**
 * Base class for AST nodes
 */
abstract class GenericNode implements Node, \Serializable
{
    /**
     * Mapping ["class name" => ["magic property" => "actual protected property"]]
     * @var array<string, array>
     */
    private static $propertyNamesCache = [];

    /**
     * Mapping ["magic property" => "actual protected property"] for current object, {@see generatePropertyNames()}
     * @var array<string, string>
     */
    protected $propertyNames = [];

    /**
     * Link to the Node containing current one
     * @var Node|null
     */
    protected $parentNode = null;

    /**
     * Flag for preventing endless recursion in {@see setParentNode()}
     * @var bool
     */
    protected $settingParentNode = false;

    /**
     * Variable overloading, exposes values of protected properties having 'p_' name prefix
     *
     * @param string $name
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function __get(string $name)
    {
        if (isset($this->propertyNames[$name])) {
            return $this->{$this->propertyNames[$name]};
        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    /**
     * Variable overloading, allows setting the $prop property if corresponding setProp() method is defined
     *
     * @param string $name
     * @param mixed  $value
     * @return void
     * @throws InvalidArgumentException
     */
    public function __set(string $name, $value)
    {
        if (method_exists($this, 'set' . $name)) {
            $this->{'set' . $name}($value);
        } else {
            throw new InvalidArgumentException(
                isset($this->propertyNames[$name])
                    ? "Property '{$name}' is read-only"
                    : "Unknown property '{$name}'"
            );
        }
    }

    /**
     * Variable overloading, checks the existence of protected property corresponding to magic one
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name)
    {
        return isset($this->propertyNames[$name]);
    }

    /**
     * Deep cloning of child nodes
     */
    public function __clone()
    {
        foreach ($this->propertyNames as $name) {
            if ($this->$name instanceof Node) {
                $this->$name = clone $this->$name;
                if ($this->$name instanceof self) {
                    $this->$name->parentNode = $this;
                } else {
                    $this->$name->setParentNode($this);
                }
            }
        }
        $this->parentNode = null;
    }

    /**
     * GenericNode only serializes its magic properties by default
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->collectProperties());
    }

    /**
     * GenericNode only serializes its magic properties by default
     * @return array
     */
    public function __serialize(): array
    {
        return $this->collectProperties();
    }

    /**
     * GenericNode only unserializes its magic properties by default
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->unserializeProperties(unserialize($serialized));
    }

    /**
     * GenericNode only unserializes its magic properties by default
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        $this->unserializeProperties($data);
    }

    /**
     * Returns an array containing all magic properties, used when serializing
     * @return array<string, mixed>
     */
    protected function collectProperties(): array
    {
        $result = [];
        foreach ($this->propertyNames as $name) {
            $result[$name] = $this->$name;
        }
        return $result;
    }

    /**
     * Unserializes properties, restoring parent node link for child nodes
     *
     * @param array<string, mixed> $properties
     */
    protected function unserializeProperties(array $properties): void
    {
        $this->generatePropertyNames();
        foreach ($properties as $k => $v) {
            if ($v instanceof self) {
                $v->parentNode = $this;
            } elseif ($v instanceof Node) {
                $v->setParentNode($this);
            }
            $this->$k = $v;
        }
    }

    /**
     * Generates mapping from magic property names to names of actual protected properties
     *
     * Magic property 'foo' corresponds to protected property 'p_foo'
     *
     * Should be called from both __construct() and unserialize() methods of GenericNode descendant that defines
     * new magic properties
     */
    final protected function generatePropertyNames(): void
    {
        if (!isset(self::$propertyNamesCache[$className = get_class($this)])) {
            self::$propertyNamesCache[$className] = [];
            foreach (array_keys(get_class_vars($className)) as $name) {
                if ('p_' === substr($name, 0, 2)) {
                    self::$propertyNamesCache[$className][substr($name, 2)] = $name;
                }
            }
        }
        $this->propertyNames = self::$propertyNamesCache[$className];
    }

    /**
     * {@inheritDoc}
     */
    public function setParentNode(Node $parent = null): void
    {
        // no-op? recursion?
        if ($parent === $this->parentNode || $this->settingParentNode) {
            return;
        }

        $this->settingParentNode = true;
        try {
            if (null !== $parent) {
                $check = $parent;
                do {
                    if ($this === $check) {
                        throw new InvalidArgumentException(
                            'Cannot set a Node or its descendant as its own parent'
                        );
                    }
                } while ($check = $check->getparentNode());
            }
            if (null !== $this->parentNode) {
                $this->parentNode->removeChild($this);
            }
            $this->parentNode = $parent;

        } finally {
            $this->settingParentNode = false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getParentNode(): ?Node
    {
        return $this->parentNode;
    }

    /**
     * {@inheritDoc}
     */
    public function replaceChild(Node $oldChild, Node $newChild): ?Node
    {
        if ($this !== $oldChild->getParentNode()) {
            throw new InvalidArgumentException(
                'First argument to replaceChild() is not a child of current node'
            );
        }
        // no-op?
        if ($newChild === $oldChild) {
            return $newChild;
        }
        // prevent the same child node in several places
        if ($this === $newChild->getParentNode()) {
            $this->removeChild($newChild);
        }
        foreach ($this->propertyNames as $name => $propertyName) {
            if ($oldChild === $this->$propertyName) {
                if (!method_exists($this, 'set' . $name)) {
                    throw new InvalidArgumentException("Property '{$name}' is read-only");
                }
                $this->{'set' . $name}($newChild);
                return $newChild;
            }
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function removeChild(Node $child): ?Node
    {
        if ($this !== $child->getParentNode()) {
            throw new InvalidArgumentException("Argument to removeChild() is not a child of current node");
        }
        foreach ($this->propertyNames as $name => $propertyName) {
            if ($child === $this->$propertyName) {
                if (!method_exists($this, 'set' . $name)) {
                    throw new InvalidArgumentException("Property '{$name}' is read-only");
                }
                $this->{'set' . $name}(null);
                return $child;
            }
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getParser(): ?Parser
    {
        if (null === $this->parentNode) {
            return null;
        } else {
            return $this->parentNode->getParser();
        }
    }

    /**
     * Returns the Parser or throws an Exception if one is not available
     *
     * @param string $as
     * @return Parser
     */
    protected function getParserOrFail(string $as): Parser
    {
        if (null !== ($parser = $this->getParser())) {
            return $parser;
        }
        throw new InvalidArgumentException(sprintf("Passed a string as %s without a Parser available", $as));
    }

    /**
     * Sets the given property to the given value, takes care of updating parentNode info
     *
     * A helper for __set() / replaceChild() / removeChild() / setWhatever() methods. As the method accepts any
     * Node instance as $value, more stringent class / interface checks should be performed in calling methods.
     *
     * @template Prop of Node
     * @param Prop|null $property
     * @param Prop|null $value
     */
    protected function setProperty(?Node &$property, ?Node $value): void
    {
        if ($value === $property) {
            // no-op
            return;
        }
        if (null !== $value) {
            // we do not allow the same node in different places
            if ($this === $value->getParentNode()) {
                $this->removeChild($value);
            }
            $value->setParentNode($this);
        }

        [$oldValue, $property] = [$property, $value];

        if (null !== $oldValue) {
            if ($oldValue instanceof self) {
                $oldValue->parentNode = null;
            } else {
                $oldValue->setParentNode(null);
            }
        }
    }

    /**
     * Sets the given property to the given value, takes care of updating parentNode info, does not allow nulls
     *
     * @template Prop of Node
     * @param Prop $property
     * @param Prop $value
     */
    protected function setRequiredProperty(Node &$property, Node $value): void
    {
        if ($value !== $property) {
            $this->setProperty($property, $value);
        }
    }
}
