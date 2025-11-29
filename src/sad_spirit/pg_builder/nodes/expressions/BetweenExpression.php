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
    enums\BetweenPredicate,
    enums\ScalarExpressionAssociativity,
    enums\ScalarExpressionPrecedence,
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
};

/**
 * AST node representing [NOT] BETWEEN expression
 *
 * @property ScalarExpression $argument
 * @property ScalarExpression $left
 * @property ScalarExpression $right
 * @property BetweenPredicate $operator
 */
class BetweenExpression extends NegatableExpression
{
    /** @internal Maps to `$argument` magic property, use the latter instead */
    protected ScalarExpression $p_argument;
    /** @internal Maps to `$left` magic property, use the latter instead */
    protected ScalarExpression $p_left;
    /** @internal Maps to `$right` magic property, use the latter instead */
    protected ScalarExpression $p_right;
    /** @internal Maps to `$operator` magic property, use the latter instead */
    protected BetweenPredicate $p_operator;

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $left,
        ScalarExpression $right,
        BetweenPredicate $operator = BetweenPredicate::BETWEEN,
        bool $not = false
    ) {
        if ($argument === $left || $argument === $right || $left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for argument / left bound / right bound");
        }

        $this->generatePropertyNames();
        $this->setOperator($operator);

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->p_not = $not;
    }

    /** @internal Support method for `$argument` magic property, use the property instead */
    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    /** @internal Support method for `$left` magic property, use the property instead */
    public function setLeft(ScalarExpression $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    /** @internal Support method for `$right` magic property, use the property instead */
    public function setRight(ScalarExpression $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    /** @internal Support method for `$operator` magic property, use the property instead */
    public function setOperator(BetweenPredicate $operator): void
    {
        $this->p_operator = $operator;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkBetweenExpression($this);
    }

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        return ScalarExpressionPrecedence::BETWEEN;
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::NONE;
    }
}
