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
 *
 * @template TKey of array-key
 * @template T of Node
 * @template TListInput
 * @template TInput
 * @implements NodeList<TKey, T, TListInput>
 */
abstract class GenericNodeList extends GenericNode implements NodeList
{
    /**
     * Child nodes available through ArrayAccess
     * @var array<TKey,T>
     */
    protected array $offsets = [];

    /**
     * Instances of these classes / interfaces will be allowed as list elements (Node is always checked)
     *
     * @return string[]
     */
    protected static function getAllowedElementClasses(): array
    {
        return [Node::class];
    }

    /**
     * Constructor, populates the list
     *
     * $list can be
     *  - an iterable containing "compatible" values
     *  - a string if Parser is available
     *
     * @param TListInput|null $list
     */
    public function __construct($list = null)
    {
        if (null !== $list) {
            $this->replace($list);
        }
    }

    /**
     * Deep cloning of child nodes
     */
    public function __clone()
    {
        parent::__clone();
        foreach ($this->offsets as &$node) {
            $node = clone $node;
            if ($node instanceof GenericNode) {
                $node->parentNode = $this;
            } else {
                $node->setParentNode($this);
            }
        }
    }

    /**
     * GenericNodeList only serializes its $offsets property by default
     * @return array
     */
    public function __serialize(): array
    {
        return $this->offsets;
    }

    /**
     * GenericNodeList only unserializes its $offsets property by default
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        /** @phpstan-var array<TKey, T> $data */
        $this->offsets = $data;
        $this->updateParentNodeOnOffsets();
    }

    /**
     * Restores the parent node link for array offsets on unserializing the object
     */
    protected function updateParentNodeOnOffsets(): void
    {
        foreach ($this->offsets as $node) {
            if ($node instanceof GenericNode) {
                $node->parentNode = $this;
            } else {
                $node->setParentNode($this);
            }
        }
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     *
     * @param TKey $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->offsets[$offset]);
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     *
     * @param TKey $offset
     * @return T
     */
    public function offsetGet($offset): Node
    {
        if (isset($this->offsets[$offset])) {
            return $this->offsets[$offset];
        }

        throw new InvalidArgumentException("Undefined offset '{$offset}'");
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     * @param TKey|null $offset
     * @param T|TInput  $value
     */
    public function offsetSet($offset, $value): void
    {
        $prepared = $this->prepareListElement($value);

        if (null === $offset) {
            $this->offsets[] = $prepared;
        } elseif (!isset($this->offsets[$offset])) {
            $this->offsets[$offset] = $prepared;
        } else {
            [$oldNode, $this->offsets[$offset]] = [$this->offsets[$offset], $prepared];
            if ($oldNode instanceof GenericNode) {
                $oldNode->parentNode = null;
            } else {
                $oldNode->setParentNode(null);
            }
        }
    }

    /**
     * Method required by ArrayAccess interface
     *
     * {@inheritDoc}
     */
    public function offsetUnset($offset): void
    {
        if (isset($this->offsets[$offset])) {
            if ($this->offsets[$offset] instanceof GenericNode) {
                $this->offsets[$offset]->parentNode = null;
            } else {
                $this->offsets[$offset]->setParentNode(null);
            }
            unset($this->offsets[$offset]);
        }
    }

    /**
     * Method required by Countable interface
     *
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->offsets);
    }

    /**
     * Method required by IteratorAggregate interface
     *
     * {@inheritDoc}
     * @return \ArrayIterator<TKey, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->offsets);
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

        $this->offsets = \array_merge($this->offsets, ...$prepared);
    }

    /**
     * {@inheritDoc}
     */
    public function replace($list): void
    {
        $prepared = $this->convertToArray($list, __METHOD__);

        foreach ($this->offsets as $node) {
            $node->setParentNode(null);
        }
        $this->offsets = $prepared;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkGenericNodeList($this);
    }

    /**
     * {@inheritDoc}
     */
    public function replaceChild(Node $oldChild, Node $newChild): ?Node
    {
        if (
            null === ($result = parent::replaceChild($oldChild, $newChild))
            && false !== ($key = \array_search($oldChild, $this->offsets, true))
        ) {
            // Since we found $oldChild in $offsets, $newChild should be of a compatible type
            /** @var T $newChild */
            $this->offsetSet($key, $newChild);
            // offsetSet() is expected to check the value itself
            return $newChild;
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function removeChild(Node $child): ?Node
    {
        if (
            null === ($result = parent::removeChild($child))
            && false !== ($key = \array_search($child, $this->offsets, true))
        ) {
            /** @phpstan-var TKey $key */
            $this->offsetUnset($key);
            return $child;
        }
        return $result;
    }

    /**
     * Returns last key in $offsets, like array_key_last()
     *
     * @return TKey|null
     */
    public function lastKey()
    {
        return \array_key_last($this->offsets);
    }

    /**
     * Ensures that "array-like" argument of merge() / replace() is either an iterable or a parseable string
     *
     * @param TListInput $array
     * @param string     $method calling method, used only for Exception messages
     * @return iterable<TInput>
     * @throws InvalidArgumentException
     */
    protected function prepareList($array, string $method): iterable
    {
        if (\is_string($array) && $this instanceof Parseable) {
            $array = $this::createFromString($this->getParserOrFail("an argument to '{$method}'"), $array);
        }
        if (!\is_iterable($array)) {
            throw new InvalidArgumentException(\sprintf(
                "%s requires either an array or an instance of Traversable, %s given",
                $method,
                \is_object($array) ? 'object(' . $array::class . ')' : \gettype($array)
            ));
        }

        /** @var iterable<TInput> $array */
        return $array;
    }

    /**
     * Converts the "array-like" argument of merge() / replace() to an actual array
     *
     * The returned array should contain only instances of Node passed through prepareListElement(),
     * it is not checked further in merge() / replace()
     *
     * @param TListInput $list
     * @param string     $method
     * @return array<TKey, T>
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
     * @param T|TInput $value
     * @return T
     */
    protected function prepareListElement($value): Node
    {
        if (\is_string($value) && $this instanceof ElementParseable) {
            $value = $this->createElementFromString($value);
        }

        $found = false;
        foreach (static::getAllowedElementClasses() as $class) {
            if ($value instanceof $class) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $shortClasses = \array_map(
                fn ($className) => ($pos = \strrpos((string) $className, '\\'))
                    ? \substr((string) $className, $pos + 1)
                    : $className,
                \array_merge(
                    [static::class],
                    \is_object($value) ? [$value::class] : [],
                    static::getAllowedElementClasses()
                )
            );

            throw new InvalidArgumentException(
                \is_object($value)
                ? \sprintf(
                    '%1$s can contain only instances of %3$s, instance of %2$s given',
                    \array_shift($shortClasses),
                    \array_shift($shortClasses),
                    \implode(" or ", $shortClasses)
                )
                : \sprintf(
                    '%s can contain only instances of %s, %s given',
                    \array_shift($shortClasses),
                    \implode(" or ", $shortClasses),
                    \gettype($value)
                )
            );
        }

        if ($this === $value->getParentNode()) {
            $this->removeChild($value);
        }
        $value->setParentNode($this);

        /** @var T $value */
        return $value;
    }
}
