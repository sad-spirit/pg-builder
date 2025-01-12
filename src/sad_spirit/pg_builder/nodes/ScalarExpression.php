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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node;

/**
 * Interface for Nodes that can appear as parts of expression
 */
interface ScalarExpression extends Node
{
    /**
     * Precedence for logical OR operator
     */
    public const PRECEDENCE_OR             = 10;

    /**
     * Precedence for logical AND operator
     */
    public const PRECEDENCE_AND            = 20;

    /**
     * Precedence for logical NOT operator
     */
    public const PRECEDENCE_NOT            = 30;

    /**
     * Precedence for various "IS <something>" operators in Postgres 9.5+
     */
    public const PRECEDENCE_IS             = 40;

    /**
     * Precedence for comparison operators in Postgres 9.5+
     */
    public const PRECEDENCE_COMPARISON     = 50;

    /**
     * Precedence for pattern matching operators LIKE / ILIKE / SIMILAR TO
     */
    public const PRECEDENCE_PATTERN        = 60;

    /**
     * Precedence for OVERLAPS operator
     */
    public const PRECEDENCE_OVERLAPS       = 70;

    /**
     * Precedence for BETWEEN operator (and its variants)
     */
    public const PRECEDENCE_BETWEEN        = 80;

    /**
     * Precedence for IN operator
     */
    public const PRECEDENCE_IN             = 90;

    /**
     * Precedence for generic infix and prefix operators
     */
    public const PRECEDENCE_GENERIC_OP     = 110;

    /**
     * Precedence for arithmetic addition / substraction
     */
    public const PRECEDENCE_ADDITION       = 130;

    /**
     * Precedence for arithmetic multiplication / division
     */
    public const PRECEDENCE_MULTIPLICATION = 140;

    /**
     * Precedence for exponentiation operator '^'
     *
     * Note that it is left-associative, contrary to usual mathematical rules
     */
    public const PRECEDENCE_EXPONENTIATION = 150;

    /**
     * Precedence for AT TIME ZONE / AT LOCAL expression
     */
    public const PRECEDENCE_TIME_ZONE      = 160;

    /**
     * Precedence for COLLATE expression
     */
    public const PRECEDENCE_COLLATE        = 170;

    /**
     * Precedence for unary plus / minus
     */
    public const PRECEDENCE_UNARY_MINUS    = 180;

    /**
     * Precedence for PostgreSQL's typecast operator '::'
     */
    public const PRECEDENCE_TYPECAST       = 190;

    /**
     * Precedence for base elements of expressions, see c_expr in original grammar
     */
    public const PRECEDENCE_ATOM           = 666;

    /**
     * Setting returned by getAssociativity() for right-associative operators
     */
    public const ASSOCIATIVE_RIGHT = 'right';

    /**
     * Setting returned by getAssociativity() for left-associative operators
     */
    public const ASSOCIATIVE_LEFT  = 'left';

    /**
     * Setting returned by getAssociativity() for non-associative operators
     */
    public const ASSOCIATIVE_NONE  = 'nonassoc';

    /**
     * Returns the integer value specifying relative precedence of this ScalarExpression
     *
     * https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-PRECEDENCE
     *
     * @return int One of the PRECEDENCE_* constants
     */
    public function getPrecedence(): int;

    /**
     * Returns the associativity of this ScalarExpression
     *
     * https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-PRECEDENCE
     *
     * @return string One of the ASSOCIATIVE_* constants
     */
    public function getAssociativity(): string;
}
