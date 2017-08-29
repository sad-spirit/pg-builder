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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use ArrayAccess,
    IteratorAggregate,
    Countable,
    ArrayIterator,
    Traversable;

/**
 * An array that enforces the type of its elements
 *
 * While the class is called "NodeList" its elements are not strictly required to
 * be instances of Node, though it is always used that way in the library
 *
 * Inspired by PEAR's PHP_ArrayOf class
 */
abstract class NodeList extends Node implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * Child nodes available through ArrayAccess
     * @var Node[]
     */
    protected $nodes;

    /**
     * Deep cloning of child nodes
     */
    public function __clone()
    {
        parent::__clone();
        foreach ($this->nodes as &$node) {
            if ($node instanceof Node) {
                $node = clone $node;
                $node->setParentNode($this);
            }
        }
    }

    /**
     * Limits serialization to only $props and $nodes properties
     */
    function __sleep()
    {
        return array_merge(parent::__sleep(), array('nodes'));
    }

    /**
     * Restores the parent node link for child nodes on unserializing the object
     */
    function __wakeup()
    {
        parent::__wakeup();
        foreach ($this->nodes as $node) {
            if ($node instanceof Node) {
                $node->setParentNode($this);
            }
        }
    }

    public function __construct($array = null)
    {
        $this->replace($array ?: array());
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->nodes);
    }

    public function offsetGet($offset)
    {
        if (array_key_exists($offset, $this->nodes)) {
            return $this->nodes[$offset];
        }

        throw new exceptions\InvalidArgumentException("Undefined offset '{$offset}'");
    }

    public function offsetSet($offset, $value)
    {
        $this->normalizeElement($offset, $value);

        if ($value instanceof Node) {
            if ($this === $value->getParentNode()) {
                $this->removeChild($value);
            }
            $value->setParentNode($this);
        }
        if (null === $offset) {
            $this->nodes[] = $value;
        } else {
            if (isset($this->nodes[$offset]) && $this->nodes[$offset] instanceof Node) {
                $this->nodes[$offset]->setParentNode(null);
            }
            $this->nodes[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        if (array_key_exists($offset, $this->nodes)) {
            if ($this->nodes[$offset] instanceof Node) {
                $this->nodes[$offset]->setParentNode(null);
            }
            unset($this->nodes[$offset]);
        }
    }

    public function count()
    {
        return count($this->nodes);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->nodes);
    }

    public function merge()
    {
        $args = func_get_args();
        foreach ($args as &$array) {
            $this->normalizeArray($array, __METHOD__);

            foreach ($array as $i => &$v) {
                if (ctype_digit((string)$i)) {
                    $this[] = $v;
                } else {
                    $this->offsetSet($i, $v);
                }
            }
        }
    }

    public function replace($array)
    {
        $this->normalizeArray($array, __METHOD__);

        $this->nodes = array();
        foreach ($array as $offset => $value) {
            $this->offsetSet($offset, $value);
        }
    }

    protected function normalizeArray(&$array, $method)
    {
        if (is_string($array) && $this instanceof Parseable) {
            if (!($parser = $this->getParser())) {
                throw new exceptions\InvalidArgumentException("Passed a string to method '{$method}' without a Parser available");
            }
            $array = call_user_func(array(get_class($this), 'createFromString'), $parser, $array);
        }
        if (!is_array($array) && !($array instanceof Traversable)) {
            throw new exceptions\InvalidArgumentException(sprintf(
                "%s requires either an array or an instance of Traversable, %s given",
                $method, is_object($array) ? 'object(' . get_class($array) . ')' : gettype($array)
            ));
        }
    }

    abstract protected function normalizeElement(&$offset, &$value);

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkGenericNodeList($this);
    }

    public function replaceChild(Node $oldChild, Node $newChild)
    {
        if (!($result = parent::replaceChild($oldChild, $newChild))) {
            if (false !== ($key = array_search($oldChild, $this->nodes, true))) {
                $this->offsetSet($key, $newChild); // offsetSet() is expected to check the value itself
                return $newChild;
            }
        }
        return $result;
    }

    public function removeChild(Node $child)
    {
        if (!($result = parent::removeChild($child))) {
            if (false !== ($key = array_search($child, $this->nodes, true))) {
                $this->offsetUnset($key);
                return $child;
            }
        }
        return $result;
    }
}