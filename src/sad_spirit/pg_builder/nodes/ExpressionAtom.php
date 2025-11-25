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

use sad_spirit\pg_builder\enums\ScalarExpressionAssociativity;
use sad_spirit\pg_builder\enums\ScalarExpressionPrecedence;

/**
 * Implements getPrecedence() and getAssociativity() methods suitable for expression "atoms"
 *
 * Atoms correspond to `c_expr` production in PostgreSQL's grammar
 *
 * @psalm-require-implements ScalarExpression
 */
trait ExpressionAtom
{
    /**
     * Atoms have the highest precedence and generally do not require parentheses added when building SQL
     */
    public function getPrecedence(): ScalarExpressionPrecedence
    {
        return ScalarExpressionPrecedence::ATOM;
    }

    /**
     * Associativity does not really make sense for atoms
     */
    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::NONE;
    }
}
