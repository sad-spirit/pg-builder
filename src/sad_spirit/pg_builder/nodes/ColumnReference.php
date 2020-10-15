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
    exceptions\InvalidArgumentException,
    exceptions\SyntaxException,
    TreeWalker
};

/**
 * Represents a (possibly qualified) column reference. The last item may also be a '*'
 *
 * @property-read Identifier|null $catalog
 * @property-read Identifier|null $schema
 * @property-read Identifier|null $relation
 * @property-read Identifier|Star $column
 */
class ColumnReference extends GenericNode implements ScalarExpression
{
    use NonRecursiveNode;
    use ExpressionAtom;

    protected $props = [
        'catalog'  => null,
        'schema'   => null,
        'relation' => null,
        'column'   => null
    ];

    /**
     * ColumnReference constructor, requires at least one name part, accepts up to four
     *
     * @param string|Identifier|Star ...$parts
     * @noinspection PhpMissingBreakStatementInspection
     */
    public function __construct(...$parts)
    {
        switch (count($parts)) {
            case 4:
                $this->props['catalog'] = $this->expectIdentifier(array_shift($parts), 'catalog');
                $this->props['catalog']->setParentNode($this);
                // fall-through is intentional
            case 3:
                $this->props['schema'] = $this->expectIdentifier(array_shift($parts), 'schema');
                $this->props['schema']->setParentNode($this);
                // fall-through is intentional
            case 2:
                $this->props['relation'] = $this->expectIdentifier(array_shift($parts), 'relation');
                $this->props['relation']->setParentNode($this);
                // fall-through is intentional
            case 1:
                $this->props['column'] = $this->expectIdentifierOrStar(array_shift($parts));
                $this->props['column']->setParentNode($this);
                break;

            case 0:
                throw new InvalidArgumentException(__CLASS__ . ' constructor expects at least one name part');
            default:
                throw new SyntaxException("Too many dots in column reference: " . implode('.', $parts));
        }
    }

    /**
     * Tries to convert last part of column reference to Identifier or Star
     *
     * @param mixed $namePart
     * @return Identifier|Star
     */
    private function expectIdentifierOrStar($namePart)
    {
        if ($namePart instanceof Star) {
            return $namePart;
        } elseif ('*' === (string)$namePart) {
            return new Star();
        } else {
            return $this->expectIdentifier($namePart, 'column');
        }
    }

    /**
     * Tries to convert part of column reference to Identifier
     *
     * @param mixed $namePart
     * @param string $index
     * @return Identifier
     */
    private function expectIdentifier($namePart, string $index): Identifier
    {
        if ($namePart instanceof Identifier) {
            return $namePart;
        }
        try {
            return new Identifier($namePart);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(sprintf(
                "%s: %s part of column reference could not be converted to Identifier; %s",
                __CLASS__,
                $index,
                $e->getMessage()
            ));
        }
    }

    public function serialize(): string
    {
        return serialize(array_map(function ($prop) {
            if (null !== $prop) {
                return $prop instanceof Identifier ? $prop->value : '';
            }
            return null;
        }, $this->props));
    }

    public function unserialize($serialized)
    {
        $this->props = array_map(function ($prop) {
            if (null !== $prop) {
                $prop = '' === $prop ? new Star() : new Identifier($prop);
                $prop->parentNode = $this;
            }
            return $prop;
        }, unserialize($serialized));
    }

    /**
     * Returns the string representation of the node, with double quotes added as needed
     *
     * @return string
     */
    public function __toString()
    {
        return (null === $this->props['catalog'] ? '' : (string)$this->props['catalog'] . '.')
               . (null === $this->props['schema'] ? '' : (string)$this->props['schema'] . '.')
               . (null === $this->props['relation'] ? '' : (string)$this->props['relation'] . '.')
               . (string)$this->props['column'];
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkColumnReference($this);
    }
}
