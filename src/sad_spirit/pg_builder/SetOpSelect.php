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
