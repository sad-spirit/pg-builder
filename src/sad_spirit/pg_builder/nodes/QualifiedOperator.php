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
    Lexer,
    TreeWalker,
    exceptions\InvalidArgumentException,
    exceptions\SyntaxException
};

/**
 * Represents the OPERATOR(...) construct with possibly specified catalog and schema names
 *
 * @property-read Identifier|null $catalog
 * @property-read Identifier|null $schema
 * @property-read string          $operator
 */
class QualifiedOperator extends GenericNode implements \Stringable
{
    use NonRecursiveNode;

    protected Identifier|null $p_catalog = null;
    protected Identifier|null $p_schema;
    protected string $p_operator;

    /**
     * QualifiedOperator constructor, requires at least the operator, accepts up to three name parts
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
                if ($this === $this->p_schema->parentNode) {
                    throw new InvalidArgumentException(
                        "Cannot use the same Node for different parts of QualifiedOperator"
                    );
                }
                $this->p_schema->setParentNode($this);
                // fall-through is intentional
            case 1:
                $this->p_operator = $this->expectOperator(\array_shift($nameParts));
                break;

            case 0:
                throw new InvalidArgumentException(self::class . ' expects operator name parts, none given');
            default:
                throw new SyntaxException("Too many dots in qualified name: " . \implode('.', $nameParts));
        }
    }

    /**
     * Tries to convert part of operator name to Identifier
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
                "%s: %s part of operator name could not be converted to Identifier; %s",
                self::class,
                $index,
                $e->getMessage()
            ));
        }
    }

    /**
     * Ensures that the last part of the qualified name looks like an operator
     */
    private function expectOperator(Identifier|string $namePart): string
    {
        if (!\is_string($namePart)) {
            throw new InvalidArgumentException(\sprintf(
                '%s requires a string for an operator, %s given',
                self::class,
                'object(' . $namePart::class . ')'
            ));
        }

        if (\strlen($namePart) !== \strspn($namePart, Lexer::CHARS_OPERATOR)) {
            throw new SyntaxException(\sprintf(
                "%s: '%s' does not look like a valid operator string",
                self::class,
                $namePart
            ));
        }

        return $namePart;
    }

    /**
     * Returns the string representation of the node, with double quotes added as needed
     */
    public function __toString(): string
    {
        return 'operator('
            . (null === $this->p_catalog ? '' : (string)$this->p_catalog . '.')
            . (null === $this->p_schema ? '' : (string)$this->p_schema . '.')
            . $this->p_operator . ')';
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkQualifiedOperator($this);
    }
}
