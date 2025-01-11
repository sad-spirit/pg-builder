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
 * Base class for JSON query expressions
 *
 * Roughly corresponds to json_api_common_syntax production without json_as_path_name_clause_opt, as the latter
 * is only used by json_table() and causes an error when actually appearing in JSON query functions
 *
 * @property JsonFormattedValue $context
 * @property ScalarExpression   $path
 * @property JsonArgumentList   $passing
 */
abstract class JsonQueryCommon extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected JsonArgumentList $p_passing;

    public function __construct(
        protected JsonFormattedValue $p_context,
        protected ScalarExpression $p_path,
        ?JsonArgumentList $passing = null
    ) {
        $this->generatePropertyNames();
        $this->p_context->setParentNode($this);
        $this->p_path->setParentNode($this);

        $this->p_passing = $passing ?? new JsonArgumentList();
        $this->p_passing->setParentNode($this);
    }

    public function setContext(JsonFormattedValue $context): void
    {
        $this->setRequiredProperty($this->p_context, $context);
    }

    public function setPath(ScalarExpression $path): void
    {
        $this->setRequiredProperty($this->p_path, $path);
    }
}
