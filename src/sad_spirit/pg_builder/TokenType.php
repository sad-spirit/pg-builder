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
 * Contains types for tokens created by Lexer
 *
 * Tokens of generic types (except IDENTIFIER) are not created in the package, those types are mostly used for
 * matching Tokens of specific types.
 */
enum TokenType: int
{
    /* Generic types */
    case LITERAL                = 1 << 8;
    case PARAMETER              = 1 << 9;
    case SPECIAL                = 1 << 10;
    case IDENTIFIER             = 1 << 11;
    case KEYWORD                = 1 << 12;

    /* Literal types */
    case STRING                 = self::LITERAL->value | 1 << 0;
    case BINARY_STRING          = self::LITERAL->value | 1 << 1;
    case HEX_STRING             = self::LITERAL->value | 1 << 2;
    // I think this is just noise right now, behaves as simple string
    case NCHAR_STRING           = self::LITERAL->value | 1 << 3;
    // String with Unicode escapes, will only appear in Lexer, never in resultant TokenStream
    case UNICODE_STRING         = self::LITERAL->value | 1 << 4;
    case INTEGER                = self::LITERAL->value | 1 << 5;
    case FLOAT                  = self::LITERAL->value | 1 << 6;

    /* Parameter types */
    case POSITIONAL_PARAM       = self::PARAMETER->value | 1 << 0;
    case NAMED_PARAM            = self::PARAMETER->value | 1 << 1;

    /* Special characters and operators */
    case SPECIAL_CHAR           = self::SPECIAL->value | 1 << 0;
    case TYPECAST               = self::SPECIAL->value | 1 << 1;
    case COLON_EQUALS           = self::SPECIAL->value | 1 << 2;
    case OPERATOR               = self::SPECIAL->value | 1 << 3;
    case INEQUALITY             = self::SPECIAL->value | 1 << 4;
    case EQUALS_GREATER         = self::SPECIAL->value | 1 << 5;

    // Identifier with Unicode escapes, will only appear in Lexer, never in resultant TokenStream
    case UNICODE_IDENTIFIER     = self::IDENTIFIER->value | 1 << 0;

    /* Keywords, as in src/include/parser/keywords.h */
    case UNRESERVED_KEYWORD     = self::KEYWORD->value | 1 << 0;
    case COL_NAME_KEYWORD       = self::KEYWORD->value | 1 << 1;
    case TYPE_FUNC_NAME_KEYWORD = self::KEYWORD->value | 1 << 2;
    case RESERVED_KEYWORD       = self::KEYWORD->value | 1 << 3;

    /** Signals end of input */
    case EOF                    = 1 << 16;

    private const CHECK_UTF8 = [
        'STRING'        => true,
        'NCHAR_STRING'  => true,
        'NAMED_PARAM'   => true,
        'IDENTIFIER'    => true
    ];

    /**
     * Returns a human-readable representation for the token type
     */
    public function toString(): string
    {
        return match ($this) {
            self::EOF               => 'end of input',
            self::STRING            => 'string literal',
            self::BINARY_STRING     => 'binary string literal',
            self::HEX_STRING        => 'hexadecimal string literal',
            self::NCHAR_STRING      => 'nchar string literal',
            self::INTEGER           => 'integer literal',
            self::FLOAT             => 'numeric literal',
            self::POSITIONAL_PARAM  => 'positional parameter',
            self::NAMED_PARAM       => 'named parameter',
            self::OPERATOR          => 'operator',
            self::TYPECAST          => 'typecast operator',
            self::COLON_EQUALS,
            self::EQUALS_GREATER    => 'named argument mark',
            self::SPECIAL_CHAR      => 'special character',
            self::INEQUALITY        => 'comparison operator',
            self::IDENTIFIER        => 'identifier',
            default                 => (0 !== ($this->value & self::KEYWORD->value))
                ? 'keyword'
                : throw new exceptions\InvalidArgumentException("Unexpected token $this->name")
        };
    }

    /**
     * Returns whether Token of this type should be checked for correct UTF-8 encoding
     */
    public function needsUtf8Check(): bool
    {
        return isset(self::CHECK_UTF8[$this->name]);
    }
}
