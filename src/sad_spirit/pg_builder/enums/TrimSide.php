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
 * Contains possible side arguments for `TRIM()` expression
 */
enum TrimSide: string
{
    use CreateFromKeywords;
    use CreateKeywords;

    case LEADING  = 'leading';
    case TRAILING = 'trailing';
    case BOTH     = 'both';
}
