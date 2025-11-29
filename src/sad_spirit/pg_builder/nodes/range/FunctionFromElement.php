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

use sad_spirit\pg_builder\NodeList;
use sad_spirit\pg_builder\nodes\{
    Identifier,
    lists\ColumnDefinitionList,
    lists\IdentifierList
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Base class for function invocations in FROM clause: either plain function calls or ROWS FROM (...) expressions
 *
 * These elements can have ColumnDefinition column aliases and optional WITH ORDINALITY flags in addition to LATERAL
 *
 * @property IdentifierList|ColumnDefinitionList|null $columnAliases
 * @property bool                                     $withOrdinality
 */
abstract class FunctionFromElement extends LateralFromElement
{
    /**
     * @var IdentifierList|ColumnDefinitionList|null
     * @internal Maps to `$columnAliases` magic property, use the latter instead
     */
    protected NodeList|null $p_columnAliases = null;
    /** @internal Maps to `$withOrdinality` magic property, use the latter instead */
    protected bool $p_withOrdinality = false;

    /**
     * Sets table and column aliases for a function call in FROM clause
     *
     * Unlike aliases for a table in FROM, column aliases here can specify types
     *
     * @param IdentifierList|ColumnDefinitionList|null $columnAliases
     */
    public function setAlias(?Identifier $tableAlias, ?NodeList $columnAliases = null): void
    {
        $this->setTableAlias($tableAlias);
        $this->setColumnAliases($columnAliases);
    }

    /**
     * {@inheritDoc}
     * @param IdentifierList|ColumnDefinitionList|null $columnAliases
     */
    public function setColumnAliases(?NodeList $columnAliases): void
    {
        if (
            null !== $columnAliases
            && !$columnAliases instanceof IdentifierList
            && !$columnAliases instanceof ColumnDefinitionList
        ) {
            throw new InvalidArgumentException(\sprintf(
                '%s expects an instance of either IdentifierList or ColumnDefinitionList, %s given',
                __METHOD__,
                $columnAliases::class
            ));
        }

        $this->setProperty($this->p_columnAliases, $columnAliases);
    }

    /** @internal Support method for `$withOrdinality` magic property, use the property instead */
    public function setWithOrdinality(bool $ordinality): void
    {
        $this->p_withOrdinality = $ordinality;
    }
}
