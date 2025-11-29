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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\{
    FunctionLike,
    GenericNode,
    lists\ColumnDefinitionList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a function call inside ROWS FROM construct
 *
 * Cannot use `range\FunctionCall` instead as it has a lot more properties
 *
 * @property-read FunctionLike         $function
 * @property      ColumnDefinitionList $columnAliases
 */
class RowsFromElement extends GenericNode
{
    /** @internal Maps to `$function` magic property, use the latter instead */
    protected FunctionLike $p_function;
    /** @internal Maps to `$columnAliases` magic property, use the latter instead */
    protected ColumnDefinitionList $p_columnAliases;

    public function __construct(FunctionLike $function, ?ColumnDefinitionList $columnAliases = null)
    {
        $this->generatePropertyNames();

        $this->p_function = $function;
        $this->p_function->setParentNode($this);

        $this->p_columnAliases = $columnAliases ?? new ColumnDefinitionList();
        $this->p_columnAliases->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkRowsFromElement($this);
    }
}
