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
    GenericNode,
    NonRecursiveNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST Node representing `merge_action()` construct
 *
 * Added in Postgres 17 together with support for `RETURNING` clauses in `MERGE` statements.
 *
 * Unfortunately, this construct can be represented neither by
 * {@see \sad_spirit\pg_builder\nodes\expressions\SQLValueFunction SQLValueFunction}, as it requires parentheses,
 * nor by {@see \sad_spirit\pg_builder\nodes\expressions\SystemFunctionCall SystemFunctionCall},
 * as it cannot have arguments.
 *
 * It is an error for it to appear outside `RETURNING` clause of `MERGE` statement,
 * so this class does not implement `FunctionLike`.
 */
class MergeAction extends GenericNode implements ScalarExpression
{
    use NonRecursiveNode;
    use ExpressionAtom;

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeAction($this);
    }
}
