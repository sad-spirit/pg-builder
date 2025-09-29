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
class ColumnReference extends GenericNode implements ScalarExpression, \Stringable
{
    use NonRecursiveNode;
    use ExpressionAtom;

    protected ?Identifier $p_catalog = null;
    protected ?Identifier $p_schema = null;
    protected ?Identifier $p_relation = null;
    protected Identifier|Star $p_column;

    /**
     * ColumnReference constructor, requires at least one name part, accepts up to four
     *
     * @noinspection PhpMissingBreakStatementInspection
     */
    public function __construct(string|Identifier|Star ...$parts)
    {
        $this->generatePropertyNames();

        switch (\count($parts)) {
            case 4:
                $this->p_catalog = $this->expectIdentifier(\array_shift($parts), 'catalog');
                $this->p_catalog->setParentNode($this);
                // fall-through is intentional
            case 3:
                $this->p_schema = $this->expectIdentifier(\array_shift($parts), 'schema');
                if ($this === $this->p_schema->getParentNode()) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of ColumnReference"
                    );
                }
                $this->p_schema->setParentNode($this);
                // fall-through is intentional
            case 2:
                $this->p_relation = $this->expectIdentifier(\array_shift($parts), 'relation');
                if ($this === $this->p_relation->getParentNode()) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of ColumnReference"
                    );
                }
                $this->p_relation->setParentNode($this);
                // fall-through is intentional
            case 1:
                $this->p_column = $this->expectIdentifierOrStar(\array_shift($parts));
                if ($this === $this->p_column->getParentNode()) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of ColumnReference"
                    );
                }
                $this->p_column->setParentNode($this);
                break;

            case 0:
                throw new InvalidArgumentException(self::class . ' constructor expects at least one name part');
            default:
                throw new SyntaxException("Too many dots in column reference: " . \implode('.', $parts));
        }
    }

    /**
     * Tries to convert last part of column reference to Identifier or Star
     */
    private function expectIdentifierOrStar(string|Identifier|Star $namePart): Identifier|Star
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
     */
    private function expectIdentifier(string|Identifier|Star $namePart, string $index): Identifier
    {
        if ($namePart instanceof Identifier) {
            return $namePart;
        } elseif ($namePart instanceof Star) {
            throw new InvalidArgumentException(\sprintf(
                "%s: instance of Star can only be used for the 'column' part, found one for '%s' part",
                self::class,
                $index
            ));
        }
        try {
            return new Identifier($namePart);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(\sprintf(
                "%s: %s part of column reference could not be converted to Identifier; %s",
                self::class,
                $index,
                $e->getMessage()
            ));
        }
    }

    public function __serialize(): array
    {
        return \array_map(
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
        \array_walk($properties, function ($v, $k): void {
            if (null !== $v) {
                $name        = $this->propertyNames[$k];
                $this->$name = '' === $v ? new Star() : new Identifier($v);
                $this->$name->parentNode = \WeakReference::create($this);
            }
        });
    }

    /**
     * Returns the string representation of the node, with double quotes added as needed
     *
     * @return string
     */
    public function __toString(): string
    {
        return (null === $this->p_catalog ? '' : (string)$this->p_catalog . '.')
               . (null === $this->p_schema ? '' : (string)$this->p_schema . '.')
               . (null === $this->p_relation ? '' : (string)$this->p_relation . '.')
               . (string)$this->p_column;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkColumnReference($this);
    }
}
