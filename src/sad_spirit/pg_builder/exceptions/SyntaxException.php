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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\exceptions;

use sad_spirit\pg_builder\{
    Exception,
    Token
};

/**
 * Thrown for parsing failures
 */
class SyntaxException extends \DomainException implements Exception
{
    protected static function getContext(string $source, int $position): array
    {
        return [
            substr_count(substr($source, 0, $position), "\n") + 1,
            substr($source, $position)
        ];
    }

    public static function atPosition(string $message, string $source, int $position): self
    {
        [$line, $fragment] = self::getContext($source, $position);
        return new self(sprintf(
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
     * @param int                  $type
     * @param string|string[]|null $value
     * @param Token                $actual
     * @param string               $source
     * @return SyntaxException
     */
    public static function expectationFailed(int $type, $value, Token $actual, string $source): self
    {
        [$line, $fragment] = self::getContext($source, $actual->getPosition());
        $expected          = Token::typeToString($type);
        if (null !== $value) {
            $expected .= " '" . (is_array($value) ? implode("' or '", $value) : $value) . "'";
        }
        return new self("Unexpected {$actual} (line {$line}), expecting {$expected}: {$fragment}");
    }
}
