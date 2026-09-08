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

namespace sad_spirit\pg_builder\nodes\range\graph;

use sad_spirit\pg_builder\{
    TreeWalker,
    nodes\GenericNode,
    nodes\WhereOrHavingClause
};

/**
 * Represents a parenthesized path pattern (currently parsed but not supported)
 *
 * @property      PathPattern         $expression
 * @property-read WhereOrHavingClause $where
 */
class NestedPattern extends GenericNode implements PathPrimary
{
    protected PathPattern $p_expression;
    protected WhereOrHavingClause $p_where;

    public function __construct(PathPattern $expression, ?WhereOrHavingClause $where = null)
    {
        $this->generatePropertyNames();

        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);

        $this->p_where = $where ?? new WhereOrHavingClause();
        $this->p_where->setParentNode($this);
    }

    /** @internal Support method for `$expression` magic property, use the property instead */
    public function setExpression(PathPattern $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkNestedPattern($this);
    }
}
