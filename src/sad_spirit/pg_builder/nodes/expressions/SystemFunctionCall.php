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
    ScalarExpression,
    lists\ExpressionList
};
use sad_spirit\pg_builder\enums\SystemFunctionName;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Node for function calls with special grammar productions
 *
 * This represents parts of `func_expr_common_subexpr` grammar rule that defines functions that have
 *   - an SQL keyword for a name (`coalesce`, `greatest`, `least`, `xmlconcat`);
 *   - an `expr_list` (our `ExpressionList`) for arguments.
 *
 * Formerly these calls were represented by `FunctionCall` / `FunctionExpression` with `$name` given as string,
 * but that caused at least two problems:
 *   - checks whether `$name` is a string or a `QualifiedName` had to be added everywhere;
 *   - `FunctionCall` has several other properties that are invalid for system functions.
 *
 * @property-read SystemFunctionName $name
 * @property      ExpressionList     $arguments
 */
class SystemFunctionCall extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    /** @internal Maps to `$name` magic property, use the latter instead */
    protected SystemFunctionName $p_name;
    /** @internal Maps to `$arguments` magic property, use the latter instead */
    protected ExpressionList $p_arguments;

    public function __construct(SystemFunctionName $name, ExpressionList $arguments)
    {
        $this->generatePropertyNames();

        $this->p_name      = $name;
        $this->p_arguments = $arguments;
        $this->p_arguments->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSystemFunctionCall($this);
    }
}
