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

/**
 * Implements getPrecedence() and getAssociativity() methods suitable for expression "atoms"
 *
 * Atoms correspond to c_expr production in PostgreSQL's grammar
 */
trait ExpressionAtom
{
    /**
     * Atoms have highest precedence and generally do not require parentheses added when building SQL
     *
     * @return int
     */
    public function getPrecedence(): int
    {
        return ScalarExpression::PRECEDENCE_ATOM;
    }

    /**
     * Associativity does not really make sense for atoms
     *
     * @return string
     */
    public function getAssociativity(): string
    {
        return ScalarExpression::ASSOCIATIVE_NONE;
    }
}
