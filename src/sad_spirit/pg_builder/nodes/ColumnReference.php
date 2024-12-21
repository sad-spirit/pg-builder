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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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

    /** @var Identifier|null */
    protected $p_catalog;
    /** @var Identifier|null */
    protected $p_schema;
    /** @var Identifier|null */
    protected $p_relation;
    /** @var Identifier|Star */
    protected $p_column;

    /**
     * ColumnReference constructor, requires at least one name part, accepts up to four
     *
     * @param string|Identifier|Star ...$parts
     * @noinspection PhpMissingBreakStatementInspection
     */
    public function __construct(...$parts)
    {
        $this->generatePropertyNames();

        switch (count($parts)) {
            case 4:
                $this->p_catalog = $this->expectIdentifier(array_shift($parts), 'catalog');
                $this->p_catalog->setParentNode($this);
                // fall-through is intentional
            case 3:
                $this->p_schema = $this->expectIdentifier(array_shift($parts), 'schema');
                if ($this === $this->p_schema->parentNode) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of ColumnReference"
                    );
                }
                $this->p_schema->setParentNode($this);
                // fall-through is intentional
            case 2:
                $this->p_relation = $this->expectIdentifier(array_shift($parts), 'relation');
                if ($this === $this->p_relation->parentNode) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of ColumnReference"
                    );
                }
                $this->p_relation->setParentNode($this);
                // fall-through is intentional
            case 1:
                $this->p_column = $this->expectIdentifierOrStar(array_shift($parts));
                if ($this === $this->p_column->parentNode) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of ColumnReference"
                    );
                }
                $this->p_column->setParentNode($this);
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
        } elseif ('*' === $namePart) {
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

    public function __serialize(): array
    {
        return array_map(
            function ($prop) {
                if (null !== $this->$prop) {
                    return $this->$prop instanceof Identifier ? $this->$prop->value : '';
                }
                return null;
            },
            $this->propertyNames
        );
    }

    protected function unserializeProperties(array $properties): void
    {
        $this->generatePropertyNames();
        array_walk($properties, function ($v, $k) {
            if (null !== $v) {
                $name        = $this->propertyNames[$k];
                $this->$name = '' === $v ? new Star() : new Identifier($v);
                $this->$name->parentNode = $this;
            }
        });
    }

    /**
     * Returns the string representation of the node, with double quotes added as needed
     *
     * @return string
     */
    public function __toString()
    {
        return (null === $this->p_catalog ? '' : (string)$this->p_catalog . '.')
               . (null === $this->p_schema ? '' : (string)$this->p_schema . '.')
               . (null === $this->p_relation ? '' : (string)$this->p_relation . '.')
               . (string)$this->p_column;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkColumnReference($this);
    }
}
