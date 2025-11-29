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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_scalar() expression
 *
 * @property ScalarExpression $expression
 */
class JsonScalar extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @internal Maps to `$expression` magic property, use the latter instead */
    protected ScalarExpression $p_expression;

    public function __construct(ScalarExpression $expression)
    {
        $this->generatePropertyNames();

        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);
    }

    /** @internal Support method for `$expression` magic property, use the property instead */
    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonScalar($this);
    }
}
