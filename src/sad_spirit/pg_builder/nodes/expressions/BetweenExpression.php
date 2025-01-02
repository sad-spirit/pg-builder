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
    enums\BetweenPredicate,
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
    protected ScalarExpression $p_argument;
    protected ScalarExpression $p_left;
    protected ScalarExpression $p_right;
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

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function setRight(ScalarExpression $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function setOperator(BetweenPredicate $operator): void
    {
        $this->p_operator = $operator;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkBetweenExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_BETWEEN;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
