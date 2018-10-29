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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\exceptions\SyntaxException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a (possibly qualified) column reference. The last item may also be a '*'
 *
 * @property-read Identifier|null $catalog
 * @property-read Identifier|null $schema
 * @property-read Identifier|null $relation
 * @property-read Identifier|Star $column
 */
class ColumnReference extends Node implements ScalarExpression
{
    public function __construct(array $parts)
    {
        $this->props = array(
            'catalog'  => null,
            'schema'   => null,
            'relation' => null,
            'column'   => null
        );

        $starIdx = null;
        foreach ($parts as $idx => &$part) {
            if (is_string($part)) {
                $part = '*' === $part ? new Star() : new Identifier($part);

            } elseif (!($part instanceof Identifier) && !($part instanceof Star)) {
                throw new InvalidArgumentException(sprintf(
                    '%s expects an array containing strings, Identifiers or Stars, %s given at index %s',
                    __CLASS__, is_object($part) ? 'object(' . get_class($part) . ')' : gettype($part), $idx
                ));
            }
            if (null === $starIdx && $part instanceof Star) {
                $starIdx = $idx;
            }
        }
        if (null !== $starIdx && $starIdx < count($parts) - 1) {
            throw new SyntaxException("Improper use of '*' in " . implode('.', $parts));
        }

        switch (count($parts)) {
        // fall-through is intentional here
        case 4:
            $this->setNamedProperty('catalog', array_shift($parts));
        case 3:
            $this->setNamedProperty('schema', array_shift($parts));
        case 2:
            $this->setNamedProperty('relation', array_shift($parts));
        case 1:
            $this->setNamedProperty('column', array_shift($parts));
            break;

        case 0:
            throw new InvalidArgumentException(
                __CLASS__ . ' expects an array containing strings or Identifiers, empty array given'
            );

        default:
            throw new SyntaxException("Too many dots in column reference: " . implode('.', $parts));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkColumnReference($this);
    }

    /**
     * Checks in base setParentNode() are redundant as this can only contain Identifiers
     *
     * @param Node $parent
     */
    protected function setParentNode(Node $parent = null)
    {
        if ($parent && $this->parentNode && $parent !== $this->parentNode) {
            $this->parentNode->removeChild($this);
        }
        $this->parentNode = $parent;
    }
}