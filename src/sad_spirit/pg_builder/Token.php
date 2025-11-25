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

namespace sad_spirit\pg_builder;

/**
 * Interface for lexer Tokens
 */
interface Token extends \Stringable
{
    /**
     * Checks whether current token matches given type and/or value
     *
     * Possible parameters
     * * type and value (or array of possible values)
     * * just type ($type is integer, $values is null)
     *
     * @param string|string[]|null $values
     */
    public function matches(TokenType $type, array|string|null $values = null): bool;

    /**
     * Checks whether current token matches any of the given keywords
     */
    public function matchesAnyKeyword(Keyword ...$keywords): bool;

    /**
     * Returns token's position in input string
     */
    public function getPosition(): int;

    /**
     * Returns token's type
     */
    public function getType(): TokenType;

    /**
     * Returns token's Keyword, if any
     */
    public function getKeyword(): ?Keyword;

    /**
     * Returns token's value
     */
    public function getValue(): string;
}
