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
 * Represents a (possibly qualified) name of a database object like a relation, function or type name
 *
 * @property-read Identifier|null $catalog
 * @property-read Identifier|null $schema
 * @property-read Identifier      $relation
 */
class QualifiedName extends GenericNode implements \Stringable
{
    use NonRecursiveNode;

    protected ?Identifier $p_catalog = null;
    protected ?Identifier $p_schema = null;
    protected Identifier $p_relation;

    /**
     * QualifiedName constructor, requires at least one name part, accepts up to three
     *
     * @noinspection PhpMissingBreakStatementInspection
     */
    public function __construct(string|Identifier ...$nameParts)
    {
        $this->generatePropertyNames();

        switch (\count($nameParts)) {
            case 3:
                $this->p_catalog = $this->expectIdentifier(\array_shift($nameParts), 'catalog');
                $this->p_catalog->setParentNode($this);
                // fall-through is intentional
            case 2:
                $this->p_schema = $this->expectIdentifier(\array_shift($nameParts), 'schema');
                if ($this === $this->p_schema->getParentNode()) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of QualifiedName"
                    );
                }
                $this->p_schema->setParentNode($this);
                // fall-through is intentional
            case 1:
                $this->p_relation = $this->expectIdentifier(\array_shift($nameParts), 'relation');
                if ($this === $this->p_relation->getParentNode()) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of QualifiedName"
                    );
                }
                $this->p_relation->setParentNode($this);
                break;

            case 0:
                throw new InvalidArgumentException(self::class . ' constructor expects at least one name part');
            default:
                throw new SyntaxException("Too many dots in qualified name: " . \implode('.', $nameParts));
        }
    }

    /**
     * Tries to convert part of qualified name to Identifier
     */
    private function expectIdentifier(string|Identifier $namePart, string $index): Identifier
    {
        if ($namePart instanceof Identifier) {
            return $namePart;
        }
        try {
            return new Identifier($namePart);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException(\sprintf(
                "%s: %s part of qualified name could not be converted to Identifier; %s",
                self::class,
                $index,
                $e->getMessage()
            ));
        }
    }

    public function __serialize(): array
    {
        return \array_map(
            fn ($prop) => $this->$prop instanceof Identifier ? $this->$prop->value : $this->$prop,
            $this->propertyNames
        );
    }

    protected function unserializeProperties(array $properties): void
    {
        $this->generatePropertyNames();
        \array_walk($properties, function ($v, $k): void {
            if (null !== $v) {
                $name = $this->propertyNames[$k];
                $this->$name = new Identifier($v);
                $this->$name->parentNode = \WeakReference::create($this);
            }
        });
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkQualifiedName($this);
    }

    /**
     * Returns the string representation of the node, with double quotes added as needed
     */
    public function __toString(): string
    {
        return (null === $this->p_catalog ? '' : (string)$this->p_catalog . '.')
            . (null === $this->p_schema ? '' : (string)$this->p_schema . '.')
            . (string)$this->p_relation;
    }
}
