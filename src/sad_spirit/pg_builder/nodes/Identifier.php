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
    Keyword,
    Token,
    TokenType,
    TreeWalker,
    exceptions\InvalidArgumentException
};

/**
 * Represents an identifier (e.g. column name or field name)
 */
class Identifier extends GenericNode implements \Stringable
{
    use NonRecursiveNode;

    public readonly string $value;

    /**
     * Cache of check results ["identifier value" => "needs double quotes?"]
     * @var array<string, bool>
     */
    private static array $needsQuoting = [];

    public function __construct(string $value)
    {
        if ('' === $value) {
            throw new InvalidArgumentException("Identifier cannot be an empty string");
        }
        $this->value = $value;
    }

    /**
     * Creates an instance of Identifier from identifier or keyword Token
     */
    public static function createFromToken(Token $token): self
    {
        if (!$token->matches(TokenType::IDENTIFIER) && !$token->matches(TokenType::KEYWORD)) {
            throw new InvalidArgumentException(\sprintf(
                '%s requires an identifier or keyword token, %s given',
                self::class,
                $token->getType()->toString()
            ));
        }

        return new self($token->getValue());
    }

    /**
     * As Identifier cannot have any child Nodes, this just removes link to parent node
     */
    public function __clone()
    {
        $this->parentNode = null;
    }

    /**
     * Serialized representation of Identifier is its value property
     * @return array
     */
    public function __serialize(): array
    {
        return [$this->value];
    }

    /**
     * Sets the value property from given array
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        [$this->value] = $data;
    }

    /**
     * Returns the string representation of the identifier, possibly with double quotes added
     *
     * @return string
     */
    public function __toString(): string
    {
        $value = $this->value;
        // We are likely to see the same identifier again, so cache the check results
        if (!isset(self::$needsQuoting[$value])) {
            self::$needsQuoting[$value] = !\preg_match('/^[a-z_][a-z_0-9$]*$/D', $value)
                                          || (null !== Keyword::tryFrom($value));
        }
        return self::$needsQuoting[$value]
               ? '"' . \str_replace('"', '""', $value) . '"'
               : $value;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkIdentifier($this);
    }
}
