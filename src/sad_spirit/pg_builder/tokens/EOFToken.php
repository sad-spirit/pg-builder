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

namespace sad_spirit\pg_builder\tokens;

use sad_spirit\pg_builder\TokenType;

/**
 * Token representing end of input
 */
class EOFToken extends GenericToken
{
    public function getType(): TokenType
    {
        return TokenType::EOF;
    }

    public function getValue(): string
    {
        return '';
    }

    public function __toString(): string
    {
        return TokenType::EOF->toString();
    }
}
