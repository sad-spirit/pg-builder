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

namespace sad_spirit\pg_builder\exceptions;

use sad_spirit\pg_builder\{
    Exception,
    Token,
    TokenType
};

/**
 * Thrown for parsing failures
 */
class SyntaxException extends \DomainException implements Exception
{
    protected static function getContext(string $source, int $position): array
    {
        return [
            \substr_count(\substr($source, 0, $position), "\n") + 1,
            \substr($source, $position)
        ];
    }

    public static function atPosition(string $message, string $source, int $position): self
    {
        [$line, $fragment] = self::getContext($source, $position);
        return new self(\sprintf(
            "%s at position %d (line %d): %s",
            $message,
            $position,
            $line,
            $fragment
        ));
    }

    /**
     * Thrown when an actual Token in TokenStream does not match expectations
     *
     * @param TokenType            $expectedType
     * @param string|string[]|null $expectedValue
     * @param Token                $actual
     * @param string               $source
     * @return SyntaxException
     */
    public static function expectationFailed(
        TokenType $expectedType,
        string|array|null $expectedValue,
        Token $actual,
        string $source
    ): self {
        [$line, $fragment] = self::getContext($source, $actual->getPosition());
        $expected          = $expectedType->toString();
        if (null !== $expectedValue) {
            $expected .= " '" . (\implode("' or '", (array)$expectedValue)) . "'";
        }
        return new self("Unexpected {$actual} (line {$line}), expecting {$expected}: {$fragment}");
    }
}
