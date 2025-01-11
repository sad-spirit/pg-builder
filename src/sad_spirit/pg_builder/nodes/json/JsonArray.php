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

/**
 * Base class for AST nodes representing the json_array() expression
 */
abstract class JsonArray extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use ReturningProperty;

    public function __construct(?JsonReturning $returning = null)
    {
        $this->generatePropertyNames();

        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
    }
}
