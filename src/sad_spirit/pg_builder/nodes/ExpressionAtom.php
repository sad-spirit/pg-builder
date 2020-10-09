<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
