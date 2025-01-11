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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing NULLIF(first, second) construct
 *
 * @property ScalarExpression $first
 * @property ScalarExpression $second
 */
class NullIfExpression extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    protected ScalarExpression $p_first;
    protected ScalarExpression $p_second;

    public function __construct(ScalarExpression $first, ScalarExpression $second)
    {
        if ($first === $second) {
            throw new InvalidArgumentException("Cannot use the same Node for both arguments");
        }

        $this->generatePropertyNames();

        $this->p_first = $first;
        $this->p_first->setParentNode($this);

        $this->p_second = $second;
        $this->p_second->setParentNode($this);
    }

    public function setFirst(ScalarExpression $first): void
    {
        $this->setRequiredProperty($this->p_first, $first);
    }

    public function setSecond(ScalarExpression $second): void
    {
        $this->setRequiredProperty($this->p_second, $second);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkNullIfExpression($this);
    }
}
