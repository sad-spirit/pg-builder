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
 * Contains relative precedences for set operators applied to `SELECT`
 */
enum SetOperatorPrecedence: int
{
    /** Precedence for `UNION [ALL]` and `EXCEPT [ALL]` set operations */
    case UNION = 1;
    /** Precedence for `INTERSECT [ALL]` set operation */
    case INTERSECT = 2;
    /** Precedence for a base `SELECT` / `VALUES` statement in set operations */
    case SELECT = 3;
}
