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
     * @param TokenType            $type
     * @param string|string[]|null $values
     * @return bool
     */
    public function matches(TokenType $type, array|string|null $values = null): bool;

    /**
     * Checks whether current token matches any of the given keywords
     */
    public function matchesKeyword(Keyword ...$keywords): bool;

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
