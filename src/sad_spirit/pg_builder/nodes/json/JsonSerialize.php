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
 * AST node representing the json_serialize() expression
 *
 * @property JsonFormattedValue $expression
 */
class JsonSerialize extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use ReturningProperty;

    public function __construct(protected JsonFormattedValue $p_expression, ?JsonReturning $returning = null)
    {
        $this->generatePropertyNames();
        $this->p_expression->setParentNode($this);

        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
    }

    public function setExpression(JsonFormattedValue $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonSerialize($this);
    }
}
