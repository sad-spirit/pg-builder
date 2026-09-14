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
 * @property      ?bool                 $ignoreNulls
 */
class FunctionExpression extends FunctionCall implements ScalarExpression
{
    use ExpressionAtom;

    /** @internal Maps to `$filter` magic property, use the latter instead */
    protected ScalarExpression|null $p_filter = null;
    /** @internal Maps to `$over` magic property, use the latter instead */
    protected WindowDefinition|null $p_over = null;
    /** @internal Maps to `$withinGroup` magic property, use the latter instead */
    protected bool $p_withinGroup;
    /** @internal Maps to `$ignoreNulls` magic property, use the latter instead */
    protected ?bool $p_ignoreNulls = null;

    public function __construct(
        string|QualifiedName $funcName,
        FunctionArgumentList|Star|null $arguments = null,
        bool $distinct = false,
        bool $variadic = false,
        ?OrderByList $orderBy = null,
        bool $withinGroup = false,
        ?ScalarExpression $filter = null,
        ?WindowDefinition $over = null,
        ?bool $ignoreNulls = null
    ) {
        parent::__construct($funcName, $arguments, $distinct, $variadic, $orderBy);

        $this->p_withinGroup = $withinGroup;
        $this->p_ignoreNulls = $ignoreNulls;

        if (null !== $filter) {
            $this->p_filter = $filter;
            $this->p_filter->setParentNode($this);
        }

        if (null !== $over) {
            $this->p_over = $over;
            $this->p_over->setParentNode($this);
        }
    }

    /** @internal Support method for `$ignoreNulls` magic property, use the property instead */
    public function setIgnoreNulls(?bool $ignoreNulls = null): void
    {
        $this->p_ignoreNulls = $ignoreNulls;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkFunctionExpression($this);
    }
}
