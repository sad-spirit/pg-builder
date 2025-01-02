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
 * Contains set operators that can be applied to `SELECT` statements
 */
enum SetOperator: string
{
    use CreateFromKeywords;

    case UNION         = 'union';
    case UNION_ALL     = 'union all';
    case INTERSECT     = 'intersect';
    case INTERSECT_ALL = 'intersect all';
    case EXCEPT        = 'except';
    case EXCEPT_ALL    = 'except all';
}
