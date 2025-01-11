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

use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_array() expression with a list of expressions as argument
 *
 * @property JsonFormattedValueList $arguments
 */
class JsonArrayValueList extends JsonArray
{
    use AbsentOnNullProperty;

    protected JsonFormattedValueList $p_arguments;

    public function __construct(
        ?JsonFormattedValueList $arguments = null,
        ?bool $absentOnNull = null,
        ?JsonReturning $returning = null
    ) {
        parent::__construct($returning);

        $this->p_arguments = $arguments ?? new JsonFormattedValueList();
        $this->p_arguments->setParentNode($this);

        $this->setAbsentOnNull($absentOnNull);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonArrayValueList($this);
    }
}
