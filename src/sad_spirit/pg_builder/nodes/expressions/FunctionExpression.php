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
    FunctionCall,
    QualifiedName,
    ScalarExpression,
    Star,
    WindowDefinition,
    lists\FunctionArgumentList,
    lists\OrderByList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a function call in scalar context (func_expr in grammar)
 *
 * @property-read bool                  $withinGroup
 * @property-read ScalarExpression|null $filter
 * @property-read WindowDefinition|null $over
 */
class FunctionExpression extends FunctionCall implements ScalarExpression
{
    use ExpressionAtom;

    protected ScalarExpression|null $p_filter = null;
    protected WindowDefinition|null $p_over = null;
    protected bool $p_withinGroup;

    public function __construct(
        string|QualifiedName $funcName,
        FunctionArgumentList|Star|null $arguments = null,
        bool $distinct = false,
        bool $variadic = false,
        ?OrderByList $orderBy = null,
        bool $withinGroup = false,
        ?ScalarExpression $filter = null,
        ?WindowDefinition $over = null
    ) {
        parent::__construct($funcName, $arguments, $distinct, $variadic, $orderBy);

        $this->p_withinGroup = $withinGroup;

        if (null !== $filter) {
            $this->p_filter = $filter;
            $this->p_filter->setParentNode($this);
        }

        if (null !== $over) {
            $this->p_over = $over;
            $this->p_over->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkFunctionExpression($this);
    }
}
