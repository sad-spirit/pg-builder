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
 * AST node representing the json() expression
 *
 * @property JsonFormattedValue $expression
 */
class JsonConstructor extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use UniqueKeysProperty;

    /** @internal Maps to `$expression` magic property, use the latter instead */
    protected JsonFormattedValue $p_expression;

    public function __construct(JsonFormattedValue $expression, ?bool $uniqueKeys = null)
    {
        $this->generatePropertyNames();

        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);

        $this->p_uniqueKeys = $uniqueKeys;
    }

    /** @internal Support method for `$expression` magic property, use the property instead */
    public function setExpression(JsonFormattedValue $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonConstructor($this);
    }
}
