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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo IS [NOT] DISTINCT FROM bar" expression
 *
 * @property ScalarExpression $left
 * @property ScalarExpression $right
 */
class IsDistinctFromExpression extends NegatableExpression
{
    protected ScalarExpression $p_left;
    protected ScalarExpression $p_right;

    public function __construct(ScalarExpression $left, ScalarExpression $right, bool $not = false)
    {
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for left and right operands");
        }

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

    public function setRight(ScalarExpression $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkIsDistinctFromExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IS;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
