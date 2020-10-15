<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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
     * Properties accessible through magic __get() and (sometimes) __set() methods
     * @var array<string, null|string|bool|int|array|Node>
     */
    protected $props = [];

    /**
     * Link to the Node containing current one
     * @var Node|null
     */
    protected $parentNode = null;

    /**
     * Flag for preventing endless recursion in setParentNode()
     * @var bool
     */
    protected $settingParentNode = false;

    /**
     * Variable overloading, exposes values of $props array as properties of the object
     *
     * @param string $name
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function __get(string $name)
    {
        if (array_key_exists($name, $this->props)) {
            return $this->props[$name];

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    /**
     * Variable overloading, allows setting the $props value keyed by 'key' if setKey() method is defined
     *
     * @param string $name
     * @param mixed  $value
     * @throws InvalidArgumentException
     */
    public function __set(string $name, $value)
    {
        if (!array_key_exists($name, $this->props)) {
            throw new InvalidArgumentException("Unknown property '{$name}'");

        } elseif (method_exists($this, 'set' . $name)) {
            $this->{'set' . $name}($value);

        } else {
            throw new InvalidArgumentException("Property '{$name}' is read-only");
        }
    }

    /**
     * Variable overloading, checks the existence of $name key in $props array
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name)
    {
        return array_key_exists($name, $this->props);
    }

    /**
     * Deep cloning of child nodes
     */
    public function __clone()
    {
        foreach ($this->props as &$item) {
            if ($item instanceof Node) {
                $item = clone $item;
                if ($item instanceof self) {
                    $item->parentNode = $this;
                } else {
                    $item->setParentNode($this);
                }
            }
        }
        $this->parentNode = null;
    }

    /**
     * GenericNode only serializes its $props property by default
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->props);
    }

    /**
     * GenericNode only unserializes its $props property by default
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->props = unserialize($serialized);
        $this->updateParentNodeOnProps();
    }

    /**
     * Restores the parent node link for child nodes on unserializing the object
     */
    protected function updateParentNodeOnProps(): void
    {
        foreach ($this->props as $item) {
            if ($item instanceof self) {
                $item->parentNode = $this;
            } elseif ($item instanceof Node) {
                $item->setParentNode($this);
            }
        }
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
        if (false !== ($key = array_search($oldChild, $this->props, true))) {
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}($newChild);
            } else {
                throw new InvalidArgumentException("Property '{$key}' is read-only");
            }
            return $newChild;
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
        if (false !== ($key = array_search($child, $this->props, true))) {
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}(null);
            } else {
                throw new InvalidArgumentException("Property '{$key}' is read-only");
            }
            return $child;
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
     * Sets the property with the given name to the given value, takes care of updating parentNode info
     *
     * A helper for __set() / replaceChild() / removeChild() / setWhatever() methods. It is assumed
     * that the value is acceptable for the property, the method should only be called when value
     * was already checked.
     *
     * @param string $propertyName
     * @param mixed  $propertyValue
     */
    protected function setNamedProperty(string $propertyName, $propertyValue): void
    {
        if (!array_key_exists($propertyName, $this->props)) {
            $this->props[$propertyName] = null;

        } elseif ($propertyValue === $this->props[$propertyName]) {
            // no-op
            return;
        }
        if ($propertyValue instanceof Node) {
            // we do not allow the same node in different places
            if ($this === $propertyValue->getParentNode()) {
                $this->removeChild($propertyValue);
            }
            $propertyValue->setParentNode($this);
        }

        $oldPropertyValue = $this->props[$propertyName];
        $this->props[$propertyName] = $propertyValue;

        if ($oldPropertyValue instanceof Node) {
            $oldPropertyValue->setParentNode(null);
        }
    }
}
