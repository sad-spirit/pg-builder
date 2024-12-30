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
 * Class representing a token
 */
class Token
{
    /**
     * Values for these token types will be checked for valid UTF-8
     */
    private const NEEDS_UTF8_CHECK = [
        TokenType::STRING,
        TokenType::NCHAR_STRING,
        TokenType::NAMED_PARAM,
        TokenType::IDENTIFIER
    ];

    private TokenType $type;
    private string $value;
    private int $position;

    public function __construct(TokenType $type, string $value, int $position)
    {
        if (\in_array($type, self::NEEDS_UTF8_CHECK, true) && !preg_match('//u', $value)) {
            throw new exceptions\InvalidArgumentException(sprintf(
                "Invalid UTF-8 in %s at position %d of input: %s",
                $type->toString(),
                $position,
                $value
            ));
        }

        $this->type     = $type;
        $this->value    = $value;
        $this->position = $position;
    }

    /**
     * Returns a string representation of the token.
     *
     * @return string
     */
    public function __toString()
    {
        if (TokenType::EOF === $this->type) {
            return $this->type->toString();
        }
        return sprintf(
            "%s '%s' at position %d",
            $this->type->toString(),
            $this->value,
            $this->position
        );
    }

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
    public function matches(TokenType $type, array|string|null $values = null): bool
    {
        if ($type->value !== ($type->value & $this->type->value)) {
            return false;
        } elseif (null === $values) {
            return true;
        } else {
            return $this->value === $values
                   || (is_array($values) && in_array($this->value, $values, true));
        }
    }

    /**
     * Returns token's position in input string
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Returns token's type
     */
    public function getType(): TokenType
    {
        return $this->type;
    }

    /**
     * Returns token's value
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
