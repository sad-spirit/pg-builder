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

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\{
    ElementParseable,
    Node,
    NodeList,
    Parseable,
    TreeWalker,
    nodes\GenericNode,
    exceptions\InvalidArgumentException
};

/**
 * An array that enforces the type of its elements
 *
 * Inspired by PEAR's PHP_ArrayOf class
 */
abstract class GenericNodeList extends GenericNode implements NodeList
{
    /**
     * Child nodes available through ArrayAccess
     * @var Node[]
     */
    protected $nodes = [];

    /**
     * Instances of these classes / interfaces will be allowed as list elements (Node is always checked)
     *
     * @return string[]
     */
    protected static function getAllowedElementClasses(): array
    {
        return [];
    }

    /**
     * Constructor, populates the list
     *
     * $list can be
     *  - an iterable containing "compatible" values
     *  - a string if Parser is available
     *
     * @param iterable|string|null $list
     */
    public function __construct($list = null)
    {
        $this->replace($list ?? []);
    }

    /**
     * Deep cloning of child nodes
     */
    public function __clone()
    {
        parent::__clone();
        foreach ($this->nodes as &$node) {
            $node = clone $node;
            $node->setParentNode($this);
        }
    }

    /**
     * Limits serialization to only $props and $nodes properties
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), ['nodes']);
    }

    /**
     * Restores the parent node link for child nodes on unserializing the object
     */
    public function __wakeup()
    {
        parent::__wakeup();
        foreach ($this->nodes as $node) {
            $node->setParentNode($this);
        }
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->nodes[$offset]);
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        if (isset($this->nodes[$offset])) {
            return $this->nodes[$offset];
        }

        throw new InvalidArgumentException("Undefined offset '{$offset}'");
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->offsetSetPrepared($offset, $this->prepareListElement($value));
    }

    /**
     * Stores the given Node at the given offset
     *
     * @param int|string $offset
     * @param Node       $value
     */
    protected function offsetSetPrepared($offset, Node $value)
    {
        if (null === $offset) {
            $this->nodes[] = $value;
        } else {
            if (isset($this->nodes[$offset])) {
                $this->nodes[$offset]->setParentNode(null);
            }
            $this->nodes[$offset] = $value;
        }
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        if (array_key_exists($offset, $this->nodes)) {
            $this->nodes[$offset]->setParentNode(null);
            unset($this->nodes[$offset]);
        }
    }

    /**
     * Method required by Countable interface
     *
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->nodes);
    }

    /**
     * Method required by IteratorAggregate interface
     *
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->nodes);
    }

    /**
     * {@inheritDoc}
     */
    public function merge(...$lists): void
    {
        $prepared = [];
        foreach ($lists as $list) {
            $prepared[] = $this->convertToArray($list, __METHOD__);
        }

        $this->nodes = array_merge($this->nodes, ...$prepared);
    }

    /**
     * {@inheritDoc}
     */
    public function replace($list): void
    {
        $prepared = $this->convertToArray($list, __METHOD__);

        foreach ($this->nodes as $node) {
            $node->setParentNode(null);
        }
        $this->nodes = $prepared;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkGenericNodeList($this);
    }

    /**
     * {@inheritDoc}
     */
    public function replaceChild(Node $oldChild, Node $newChild): ?Node
    {
        if (!($result = parent::replaceChild($oldChild, $newChild))) {
            if (false !== ($key = array_search($oldChild, $this->nodes, true))) {
                $this->offsetSet($key, $newChild); // offsetSet() is expected to check the value itself
                return $newChild;
            }
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function removeChild(Node $child): ?Node
    {
        if (!($result = parent::removeChild($child))) {
            if (false !== ($key = array_search($child, $this->nodes, true))) {
                $this->offsetUnset($key);
                return $child;
            }
        }
        return $result;
    }

    /**
     * Ensures that "array-like" argument of merge() / replace() is either an iterable or a parseable string
     *
     * @param iterable|string $array
     * @param string          $method calling method, used only for Exception messages
     * @return iterable
     * @throws InvalidArgumentException
     */
    protected function prepareList($array, string $method): iterable
    {
        if (is_string($array) && $this instanceof Parseable) {
            if (!($parser = $this->getParser())) {
                throw new InvalidArgumentException(
                    "Passed a string to method '{$method}' without a Parser available"
                );
            }
            $array = static::createFromString($parser, $array);
        }
        if (!is_iterable($array)) {
            throw new InvalidArgumentException(sprintf(
                "%s requires either an array or an instance of Traversable, %s given",
                $method,
                is_object($array) ? 'object(' . get_class($array) . ')' : gettype($array)
            ));
        }

        return $array;
    }

    /**
     * Converts the "array-like" argument of merge() / replace() to an actual array
     *
     * The returned array should contain only instances of Node passed through prepareListElement(),
     * it is not checked further in merge() / replace()
     *
     * @param iterable|string $list
     * @param string $method
     * @return array
     */
    abstract protected function convertToArray($list, string $method): array;

    /**
     * Prepares the given value for addition to the list
     *
     * If the value is a string it is processed by Parser. The class / interface of value is checked against
     * the $allowedElementClasses.
     *
     * Finally, the list is set as a parent of Node. This is done here so that merge() / replace() methods
     * may work on an all or nothing principle, without possibility of merging only a part of array.
     *
     * @param mixed $value
     * @return Node
     */
    protected function prepareListElement($value): Node
    {
        if (is_string($value) && $this instanceof ElementParseable) {
            $value = $this->createElementFromString($value);
        }

        if (!$value instanceof Node) {
            throw new InvalidArgumentException(sprintf(
                "GenericNodeList can contain only instances of Node, %s given",
                is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }

        if ($classes = static::getAllowedElementClasses()) {
            $found = false;
            foreach ($classes as $class) {
                if ($value instanceof $class) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $shortClasses = array_map(function ($className) {
                    return substr($className, strrpos($className, '\\') + 1);
                }, array_merge([get_class($this), get_class($value)], $classes));

                throw new InvalidArgumentException(sprintf(
                    '%1$s can contain only instances of %3$s, instance of %2$s given',
                    array_shift($shortClasses),
                    array_shift($shortClasses),
                    implode(" or ", $shortClasses)
                ));
            }
        }

        if ($this === $value->getParentNode()) {
            $this->removeChild($value);
        }
        $value->setParentNode($this);

        return $value;
    }
}
