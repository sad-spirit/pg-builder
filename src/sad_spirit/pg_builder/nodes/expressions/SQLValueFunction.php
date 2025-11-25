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
use sad_spirit\pg_builder\enums\SQLValueFunctionName;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Node for parameterless functions with special grammar productions
 *
 * Direct interpretation of `SQLValueFunction` in `src/include/nodes/primnodes.h`
 *
 * Previously these functions were converted either to `FunctionCall` nodes with corresponding functions
 * from `pg_catalog`, or to `TypecastExpression` nodes. Better (and probably more compatible) is to keep them as-is.
 *
 * @property-read SQLValueFunctionName $name
 * @property-read NumericConstant|null $modifier
 */
class SQLValueFunction extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    protected SQLValueFunctionName $p_name;
    protected ?NumericConstant $p_modifier;

    public function __construct(SQLValueFunctionName $name, ?NumericConstant $modifier = null)
    {
        if (null !== $modifier && !$name->allowsModifiers()) {
            throw new InvalidArgumentException("SQLValueFunction '$name->value' does not accept modifiers");
        }

        $this->generatePropertyNames();
        $this->p_name     = $name;
        $this->p_modifier = $modifier;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSQLValueFunction($this);
    }
}
