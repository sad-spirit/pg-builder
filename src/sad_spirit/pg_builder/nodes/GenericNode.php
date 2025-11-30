<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
abstract class GenericNode implements Node
{
    /**
     * Mapping ["class name" => ["magic property" => "actual protected property"]]
     * @var array<class-string, array<string, string>>
     */
    private static array $propertyNamesCache = [];

    /**
     * Mapping ["magic property" => "actual protected property"] for current object
     *
     * @var array<string, string>
     * @see GenericNode::generatePropertyNames()
     * @internal
     */
    protected array $propertyNames = [];

    /**
     * Link to the Node containing current one
     * @var \WeakReference<Node>|null
     * @internal Non-package code should use `setParentNode()` / `getParentNode()`
     */
    protected ?\WeakReference $parentNode = null;

    /**
     * Flag for preventing endless recursion in setParentNode()
     * @internal Should only be used inside `setParentNode()`
     */
    protected bool $settingParentNode = false;

    /**
     * Variable overloading, exposes values of protected properties having 'p_' name prefix
     *
     * @throws InvalidArgumentException
     */
    public function __get(string $name): mixed
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
     * @throws InvalidArgumentException
     */
    public function __set(string $name, mixed $value): void
    {
        if (\method_exists($this, 'set' . $name)) {
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
     */
    public function __isset(string $name): bool
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
                    $this->$name->parentNode = \WeakReference::create($this);
                } else {
                    $this->$name->setParentNode($this);
                }
            }
        }
        $this->parentNode = null;
    }

    /**
     * GenericNode only serializes its magic properties by default
     */
    public function __serialize(): array
    {
        return $this->collectProperties();
    }

    /**
     * GenericNode only unserializes its magic properties by default
     */
    public function __unserialize(array $data): void
    {
        $this->unserializeProperties($data);
    }

    /**
     * Returns an array containing all magic properties, used when serializing
     * @return array<string, mixed>
     * @internal
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
     * @internal
     */
    protected function unserializeProperties(array $properties): void
    {
        $this->generatePropertyNames();
        foreach ($properties as $k => $v) {
            if ($v instanceof self) {
                $v->parentNode = \WeakReference::create($this);
            } elseif ($v instanceof Node) {
                $v->setParentNode($this);
            }
            $this->$k = $v;
        }
    }

    /**
     * Generates mapping from magic property names to names of actual protected properties
     *
     * Magic property `$foo` maps to protected property `$p_foo`
     *
     * Should be called from both `__construct()` and `unserialize()` methods of `GenericNode` descendant that defines
     * new magic properties
     *
     * @internal
     */
    final protected function generatePropertyNames(): void
    {
        if (!isset(self::$propertyNamesCache[$className = static::class])) {
            self::$propertyNamesCache[$className] = [];
            foreach (\array_keys(\get_class_vars($className)) as $name) {
                if (\str_starts_with($name, 'p_')) {
                    self::$propertyNamesCache[$className][\substr($name, 2)] = $name;
                }
            }
        }
        $this->propertyNames = self::$propertyNamesCache[$className];
    }

    public function setParentNode(?Node $parent): void
    {
        // no-op? recursion?
        if ($parent === $this->parentNode?->get() || $this->settingParentNode) {
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
            $this->parentNode?->get()?->removeChild($this);
            $this->parentNode = $parent ? \WeakReference::create($parent) : null;

        } finally {
            $this->settingParentNode = false;
        }
    }

    public function getParentNode(): ?Node
    {
        return $this->parentNode?->get();
    }

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
                if (!\method_exists($this, 'set' . $name)) {
                    throw new InvalidArgumentException("Property '{$name}' is read-only");
                }
                $this->{'set' . $name}($newChild);
                return $newChild;
            }
        }
        return null;
    }

    public function removeChild(Node $child): ?Node
    {
        if ($this !== $child->getParentNode()) {
            throw new InvalidArgumentException("Argument to removeChild() is not a child of current node");
        }
        foreach ($this->propertyNames as $name => $propertyName) {
            if ($child === $this->$propertyName) {
                if (!\method_exists($this, 'set' . $name)) {
                    throw new InvalidArgumentException("Property '{$name}' is read-only");
                }
                $this->{'set' . $name}(null);
                return $child;
            }
        }
        return null;
    }

    public function getParser(): ?Parser
    {
        return $this->parentNode?->get()?->getParser();
    }

    /**
     * Returns the Parser or throws an Exception if one is not available
     * @internal
     */
    protected function getParserOrFail(string $as): Parser
    {
        if (null !== $parser = $this->getParser()) {
            return $parser;
        }
        throw new InvalidArgumentException(\sprintf("Passed a string as %s without a Parser available", $as));
    }

    /**
     * Sets the given property to the given value, takes care of updating parentNode info
     *
     * A helper for `__set()` / `replaceChild()` / `removeChild()` / `setWhatever()` methods. As the method accepts any
     * `Node` instance as `$value`, more stringent class / interface checks should be performed in calling methods.
     *
     * @template Prop of Node
     * @param Prop|null $property
     * @param Prop|null $value
     * @internal
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
     * @internal
     */
    protected function setRequiredProperty(Node &$property, Node $value): void
    {
        if ($value !== $property) {
            $this->setProperty($property, $value);
        }
    }
}
