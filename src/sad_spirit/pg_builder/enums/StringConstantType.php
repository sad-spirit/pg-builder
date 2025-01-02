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

namespace sad_spirit\pg_builder\enums;

/**
 * Contains possible types for string constants
 */
enum StringConstantType: string
{
    case CHARACTER   = 'c';
    case BINARY      = 'b';
    case HEXADECIMAL = 'x';
}
