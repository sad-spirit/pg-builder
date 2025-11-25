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
use sad_spirit\pg_builder\Node;

/**
 * Interface for Nodes that can appear as parts of expression
 */
interface ScalarExpression extends Node
{
    /**
     * Returns the relative precedence of this ScalarExpression
     *
     * @link https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-PRECEDENCE
     */
    public function getPrecedence(): ScalarExpressionPrecedence;

    /**
     * Returns the associativity of this ScalarExpression
     *
     * @link https://www.postgresql.org/docs/current/sql-syntax-lexical.html#SQL-PRECEDENCE
     */
    public function getAssociativity(): ScalarExpressionAssociativity;
}
