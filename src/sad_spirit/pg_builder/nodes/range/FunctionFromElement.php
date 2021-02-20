<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
 * @psalm-property IdentifierList|ColumnDefinitionList|null $columnAliases
 *
 * @property IdentifierList|Identifier[]|ColumnDefinitionList|ColumnDefinition[]|null $columnAliases
 * @property bool                                                                     $withOrdinality
 */
abstract class FunctionFromElement extends LateralFromElement
{
    /** @var IdentifierList|ColumnDefinitionList|null */
    protected $p_columnAliases;
    /** @var bool */
    protected $p_withOrdinality = false;

    /**
     * Sets table and column aliases for a function call in FROM clause
     *
     * Unlike aliases for a table in FROM, column aliases here can specify types
     *
     * @param Identifier|null                          $tableAlias
     * @param IdentifierList|ColumnDefinitionList|null $columnAliases
     */
    public function setAlias(Identifier $tableAlias = null, NodeList $columnAliases = null): void
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
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of either IdentifierList or ColumnDefinitionList, %s given',
                __METHOD__,
                get_class($columnAliases)
            ));
        }

        $this->setProperty($this->p_columnAliases, $columnAliases);
    }

    public function setWithOrdinality(bool $ordinality): void
    {
        $this->p_withOrdinality = $ordinality;
    }
}
