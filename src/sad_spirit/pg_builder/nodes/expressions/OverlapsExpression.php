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
    exceptions\InvalidArgumentException,
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing (...) OVERLAPS (...) construct
 *
 * Both arguments can only be instances of RowExpression containing two elements, so we implement
 * this as a special subclass rather than use generic OperatorExpression
 *
 * @property RowExpression $left
 * @property RowExpression $right
 */
class OverlapsExpression extends GenericNode implements ScalarExpression
{
    protected RowExpression $p_left;
    protected RowExpression $p_right;

    public function __construct(RowExpression $left, RowExpression $right)
    {
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for left and right arguments");
        }
        if (2 !== count($left)) {
            throw new InvalidArgumentException("Wrong number of items in the left argument to OVERLAPS");
        }
        if (2 !== count($right)) {
            throw new InvalidArgumentException("Wrong number of items in the right argument to OVERLAPS");
        }

        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);
    }

    public function setLeft(RowExpression $left): void
    {
        if (2 !== count($left)) {
            throw new InvalidArgumentException("Wrong number of items in the left argument to OVERLAPS");
        }
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function setRight(RowExpression $right): void
    {
        if (2 !== count($right)) {
            throw new InvalidArgumentException("Wrong number of items in the right argument to OVERLAPS");
        }
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function dispatch(TreeWalker $walker): mixed
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
