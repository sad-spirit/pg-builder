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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\enums\SetOperator;

/**
 * Represents a set operator (UNION, INTERSECT, EXCEPT) applied to two select statements
 *
 * @property      SelectCommon $left
 * @property      SelectCommon $right
 * @property-read SetOperator  $operator
 */
class SetOpSelect extends SelectCommon
{
    protected SelectCommon $p_left;
    protected SelectCommon $p_right;
    protected SetOperator $p_operator;

    public function __construct(SelectCommon $left, SelectCommon $right, SetOperator $operator = SetOperator::UNION)
    {
        parent::__construct();

        if ($left === $right) {
            throw new exceptions\InvalidArgumentException("Cannot combine a SELECT statement with itself");
        }

        $left->setParentNode($this);
        $this->p_left = $left;

        $right->setParentNode($this);
        $this->p_right = $right;

        $this->p_operator = $operator;
    }

    public function setLeft(SelectCommon $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function setRight(SelectCommon $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSetOpSelectStatement($this);
    }

    public function getPrecedence(): int
    {
        return match ($this->p_operator) {
            SetOperator::UNION,
            SetOperator::UNION_ALL,
            SetOperator::EXCEPT,
            SetOperator::EXCEPT_ALL => self::PRECEDENCE_SETOP_UNION,

            SetOperator::INTERSECT,
            SetOperator::INTERSECT_ALL => self::PRECEDENCE_SETOP_INTERSECT
        };
    }
}
