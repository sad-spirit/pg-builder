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
    lists\ExpressionList,
    ScalarExpression
};
use sad_spirit\pg_builder\enums\TrimSide;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing TRIM(...) function call with special arguments format
 *
 * Previously this was parsed to a `FunctionExpression` node having `pg_catalog.(btrim|ltrim|rtrim)` as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * NB: we define `$arguments` as `ExpressionList` as this is what Postgres itself does (see `trim_list` grammar
 * production). However, passing more than two arguments will result in a "function does not exist" error and
 * thus Postgres only outputs at most two arguments when generating SQL in `src/backend/utils/adt/ruleutils.c`
 * Obviously, we cannot  check for function existence, so just pass on all arguments in generated SQL.
 *
 * @property      ExpressionList $arguments
 * @property-read TrimSide       $side
 */
class TrimExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected ExpressionList $p_arguments;
    protected TrimSide $p_side;

    public function __construct(ExpressionList $arguments, TrimSide $side = TrimSide::BOTH)
    {
        $this->generatePropertyNames();

        $this->p_arguments = $arguments;
        $this->p_arguments->setParentNode($this);

        $this->p_side = $side;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkTrimExpression($this);
    }
}
