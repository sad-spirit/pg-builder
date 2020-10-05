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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing (...) OVERLAPS (...) construct
 *
 * Both of the arguments can be only instances RowExpression with two elements so we implement
 * this as a special subclass rather than use generic OperatorExpression
 *
 * @property RowExpression $left
 * @property RowExpression $right
 */
class OverlapsExpression extends GenericNode implements ScalarExpression
{
    public function __construct(RowExpression $left, RowExpression $right)
    {
        $this->setLeft($left);
        $this->setRight($right);
    }

    public function setLeft(RowExpression $left): void
    {
        if (2 !== count($left)) {
            throw new InvalidArgumentException("Wrong number of items in the left argument to OVERLAPS");
        }
        $this->setNamedProperty('left', $left);
    }

    public function setRight(RowExpression $right): void
    {
        if (2 !== count($right)) {
            throw new InvalidArgumentException("Wrong number of items in the right argument to OVERLAPS");
        }
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOverlapsExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_OVERLAPS;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
