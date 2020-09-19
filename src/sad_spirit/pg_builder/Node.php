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

namespace sad_spirit\pg_builder;

/**
 * Base class for AST nodes
 */
abstract class Node
{
    /**
     * Properties accessible through magic __get() and (sometimes) __set() methods
     * @var Node[]
     */
    protected $props = array();

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
     * @throws exceptions\InvalidArgumentException
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->props)) {
            return $this->props[$name];

        } else {
            throw new exceptions\InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    /**
     * Variable overloading, allows setting the $props value keyed by 'key' if setKey() method is defined
     *
     * @param string $name
     * @param mixed  $value
     * @throws exceptions\InvalidArgumentException
     */
    public function __set($name, $value)
    {
        if (!array_key_exists($name, $this->props)) {
            throw new exceptions\InvalidArgumentException("Unknown property '{$name}'");

        } elseif (method_exists($this, 'set' . $name)) {
            $this->{'set' . $name}($value);

        } else {
            throw new exceptions\InvalidArgumentException("Property '{$name}' is read-only");
        }
    }

    /**
     * Variable overloading, checks the existence of $name key in $props array
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
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
    function __sleep()
    {
        return array('props');
    }

    /**
     * Restores the parent node link for child nodes on unserializing the object
     */
    function __wakeup()
    {
        $this->updatePropsParentNode();
    }

    /**
     * Double-dispatch method supposed to call the relevant method of TreeWalker
     *
     * @param TreeWalker $walker
     * @throws exceptions\NotImplementedException
     * @return mixed
     */
    public function dispatch(TreeWalker $walker)
    {
        throw new exceptions\NotImplementedException("Dispatch for node '" . get_class($this) . "' is not implemented");
    }

    /**
     * Adds the link to the Node containing current one
     *
     * @param Node $parent Node containing the current one, null if the link should
     *                     really be removed (when calling from removeChild())
     *
     * @throws exceptions\InvalidArgumentException When trying to set a child of a Node as its parent
     */
    protected function setParentNode(Node $parent = null)
    {
        // no-op?
        if ($parent === $this->parentNode) {
            return;
        }
        if (null !== $parent) {
            $check = $parent;
            do {
                if ($this === $check) {
                    throw new exceptions\InvalidArgumentException('Cannot set a Node or its descendant as its own parent');
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
     * Returns the node containing current one
     *
     * @return Node|null
     */
    public function getParentNode()
    {
        return $this->parentNode;
    }

    /**
     * Returns the Parser (used by some subclasses to add parts of expression in SQL string form)
     *
     * @return Parser|null
     */
    protected function getParser()
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
    protected function updatePropsParentNode()
    {
        foreach ($this->props as $prop) {
            if ($prop instanceof Node) {
                $prop->setParentNode($this);
            }
        }
    }

    /**
     * Replaces the child Node with another one
     *
     * This is a building block for methods that change the AST, see e.g. ParameterWalker
     *
     * @param Node $oldChild
     * @param Node $newChild
     * @return Node|null $newChild in case of successful replace, null otherwise
     * @throws exceptions\InvalidArgumentException
     */
    public function replaceChild(Node $oldChild, Node $newChild)
    {
        if ($this !== $oldChild->getParentNode()) {
            throw new exceptions\InvalidArgumentException("First argument to replaceChild() is not a child of current node");
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
                throw new exceptions\InvalidArgumentException("Property '{$key}' is read-only");
            }
            return $newChild;
        }
        return null;
    }

    /**
     * Removes the child Node (actually tries to store a null in a relevant property)
     *
     * @param Node $child
     * @return Node|null
     * @throws exceptions\InvalidArgumentException
     */
    public function removeChild(Node $child)
    {
        if ($this !== $child->getParentNode()) {
            throw new exceptions\InvalidArgumentException("Argument to removeChild() is not a child of current node");
        }
        if (false !== ($key = array_search($child, $this->props, true))) {
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}(null);
            } else {
                throw new exceptions\InvalidArgumentException("Property '{$key}' is read-only");
            }
            return $child;
        }
        return null;
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
    protected function setNamedProperty($propertyName, $propertyValue)
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
