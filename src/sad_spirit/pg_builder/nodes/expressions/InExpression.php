<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    SelectCommon,
    TreeWalker,
    nodes\ScalarExpression
};
use sad_spirit\pg_builder\nodes\lists\ExpressionList;

/**
 * AST node representing a [NOT] IN expression
 *
 * Cannot be an OperatorExpression due to specific right operands
 *
 * @property ScalarExpression            $left
 * @property SelectCommon|ExpressionList $right
 */
class InExpression extends NegatableExpression
{
    public function __construct(
        protected ScalarExpression $p_left,
        protected SelectCommon|ExpressionList $p_right,
        bool $not = false
    ) {
        $this->generatePropertyNames();

        $this->p_right->setParentNode($this);
        $this->p_left->setParentNode($this);

        $this->p_not = $not;
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    /**
     * Sets the subselect or a list of expressions appearing in parentheses: foo IN (...)
     */
    public function setRight(SelectCommon|ExpressionList $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkInExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IN;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
