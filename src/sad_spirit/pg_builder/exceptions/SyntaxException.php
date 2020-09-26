<?php
/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\exceptions;

use sad_spirit\pg_builder\Exception,
    sad_spirit\pg_builder\Token;

/**
 * Thrown for parsing failures
 */
class SyntaxException extends \DomainException implements Exception
{
    protected static function getContext($string, $position)
    {
        return array(
            substr_count(substr($string, 0, $position), "\n") + 1,
            substr($string, $position)
        );
    }

    public static function atPosition($message, $string, $position)
    {
        list($line, $fragment) = self::getContext($string, $position);
        return new self(sprintf(
            "%s at position %d (line %d): %s",
            $message, $position, $line, $fragment
        ));
    }

    public static function expectationFailed($type, $value, Token $actual, $string)
    {
        list($line, $fragment) = self::getContext($string, $actual->getPosition());
        if (null === $value) {
            if (is_int($type)) {
                $expected = Token::typeToString($type);
            } else {
                $expected = "'" . (is_array($type) ? implode("' or '", $type) : $type) . "'";
            }
        } else {
            $expected = Token::typeToString($type) . " with value '"
                        . (is_array($value) ? implode("' or '", $value) : $value) . "'";
        }
        return new self("Unexpected {$actual} (line {$line}), expecting {$expected}: {$fragment}");
    }
}