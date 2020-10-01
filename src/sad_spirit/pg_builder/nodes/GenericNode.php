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
abstract class GenericNode implements Node
{
    /**
     * Properties accessible through magic __get() and (sometimes) __set() methods
     * @var Node[]
     */
    protected $props = [];

    /**
     * Link to the Node containing current one
     * @var Node|null
     */
    protected $parentNode = null;

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
            }
        }
        $this->updatePropsParentNode();
        if ($this->parentNode) {
            $this->parentNode = null;
        }
    }

    /**
     * Limits serialization to only $props property
     */
    public function __sleep()
    {
        return ['props'];
    }

    /**
     * Restores the parent node link for child nodes on unserializing the object
     */
    public function __wakeup()
    {
        $this->updatePropsParentNode();
    }

    /**
     * {@inheritDoc}
     */
    public function setParentNode(Node $parent = null): void
    {
        // no-op?
        if ($parent === $this->parentNode) {
            return;
        }
        if (null !== $parent) {
            $check = $parent;
            do {
                if ($this === $check) {
                    throw new InvalidArgumentException(
                        'Cannot set a Node or its descendant as its own parent'
                    );
                }
            } while ($check = $check->getparentNode());
            // this is intentionally inside the "if (null !== $parent)" check to prevent endless recursion
            // when called from removeChild()
            if (null !== $this->parentNode) {
                $this->parentNode->removeChild($this);
            }
        }
        $this->parentNode = $parent;
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
        if (!$this->parentNode) {
            return null;
        } else {
            return $this->parentNode->getParser();
        }
    }

    /**
     * Sets this node as parent node of all nodes in $props
     */
    protected function updatePropsParentNode(): void
    {
        foreach ($this->props as $prop) {
            if ($prop instanceof Node) {
                $prop->setParentNode($this);
            }
        }
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
        if ($propertyValue instanceof Node && $this === $propertyValue->getParentNode()) {
            $this->removeChild($propertyValue);
        }
        if ($this->props[$propertyName] instanceof Node) {
            $this->props[$propertyName]->setParentNode(null);
        }

        $this->props[$propertyName] = $propertyValue;
        if ($this->props[$propertyName] instanceof Node) {
            $this->props[$propertyName]->setParentNode($this);
        }
    }
}