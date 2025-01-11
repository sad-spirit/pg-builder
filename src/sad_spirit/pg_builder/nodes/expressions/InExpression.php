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
