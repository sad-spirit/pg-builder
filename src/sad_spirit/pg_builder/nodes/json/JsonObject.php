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
 * AST node representing the json_object() expression
 *
 * @property JsonKeyValueList $arguments
 */
class JsonObject extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use AbsentOnNullProperty;
    use ReturningProperty;
    use UniqueKeysProperty;

    protected JsonKeyValueList $p_arguments;

    public function __construct(
        ?JsonKeyValueList $arguments = null,
        ?bool $absentOnNull = null,
        ?bool $uniqueKeys = null,
        ?JsonReturning $returning = null
    ) {
        $this->generatePropertyNames();

        $this->p_arguments = $arguments ?? new JsonKeyValueList();
        $this->p_arguments->setParentNode($this);

        $this->p_absentOnNull = $absentOnNull;
        $this->p_uniqueKeys = $uniqueKeys;

        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonObject($this);
    }
}
