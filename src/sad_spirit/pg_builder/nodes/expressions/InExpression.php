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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    SelectCommon,
    TreeWalker,
    enums\ScalarExpressionAssociativity,
    enums\ScalarExpressionPrecedence,
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
    protected ScalarExpression $p_left;
    protected SelectCommon|ExpressionList $p_right;

    public function __construct(ScalarExpression $left, SelectCommon|ExpressionList $right, bool $not = false)
    {
        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

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

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        return ScalarExpressionPrecedence::IN;
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::NONE;
    }
}
