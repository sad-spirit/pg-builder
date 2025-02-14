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
 * Contains relative precedences for various scalar expressions
 */
enum ScalarExpressionPrecedence: int
{
    /** Precedence for logical `OR` operator */
    case OR             = 10;
    /**  Precedence for logical `AND` operator */
    case AND            = 20;
    /** Precedence for logical `NOT` operator */
    case NOT            = 30;
    /** Precedence for various `IS <something>` operators in Postgres 9.5+ */
    case IS             = 40;
    /** Precedence for comparison operators in Postgres 9.5+ */
    case COMPARISON     = 50;
    /** Precedence for pattern matching operators `LIKE` / `ILIKE` / `SIMILAR TO` */
    case PATTERN        = 60;
    /** Precedence for `OVERLAPS` operator */
    case OVERLAPS       = 70;
    /** Precedence for `BETWEEN` operator (and its variants) */
    case BETWEEN        = 80;
    /** Precedence for `IN` operator */
    case IN             = 90;
    /** Precedence for generic operators */
    case GENERIC_OP     = 110;
    /** Precedence for arithmetic addition / subtraction */
    case ADDITION       = 130;
    /** Precedence for arithmetic multiplication / division */
    case MULTIPLICATION = 140;
    /**
     * Precedence for exponentiation operator `^`
     *
     * Note that it is left-associative, contrary to usual mathematical rules
     */
    case EXPONENTIATION = 150;
    /** Precedence for `AT TIME ZONE` / `AT LOCAL` expression */
    case TIME_ZONE      = 160;
    /** Precedence for `COLLATE` expression */
    case COLLATE        = 170;
    /** Precedence for unary plus / minus */
    case UNARY_MINUS    = 180;
    /** Precedence for PostgreSQL's typecast operator `::` */
    case TYPECAST       = 190;
    /** Precedence for base elements of expressions, see c_expr in original grammar */
    case ATOM           = 666;
}
