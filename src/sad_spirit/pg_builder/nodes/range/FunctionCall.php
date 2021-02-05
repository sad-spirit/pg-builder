<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\{
    FunctionLike,
    Identifier,
    lists\IdentifierList,
    lists\ColumnDefinitionList
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\NodeList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a function call in FROM clause
 *
 * @psalm-property IdentifierList|ColumnDefinitionList|null $columnAliases
 *
 * @property      IdentifierList|Identifier[]|ColumnDefinitionList|ColumnDefinition[]|null $columnAliases
 * @property-read FunctionLike                                                             $function
 * @property      bool                                                                     $lateral
 * @property      bool                                                                     $withOrdinality
 */
class FunctionCall extends FromElement
{
    /** @var IdentifierList|ColumnDefinitionList|null */
    protected $p_columnAliases;
    /** @var FunctionLike */
    protected $p_function;
    /** @var bool */
    protected $p_lateral = false;
    /** @var bool */
    protected $p_withOrdinality = false;

    public function __construct(FunctionLike $function)
    {
        $this->generatePropertyNames();

        $this->p_function = $function;
        $this->p_function->setParentNode($this);
    }

    public function setLateral(bool $lateral): void
    {
        $this->p_lateral = $lateral;
    }

    public function setWithOrdinality(bool $ordinality): void
    {
        $this->p_withOrdinality = $ordinality;
    }

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
        if (null === $columnAliases || $columnAliases instanceof IdentifierList) {
            parent::setAlias($tableAlias, $columnAliases);

        } elseif ($columnAliases instanceof ColumnDefinitionList) {
            parent::setAlias($tableAlias);
            $this->setProperty($this->p_columnAliases, $columnAliases);

        } else {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of either IdentifierList or ColumnDefinitionList, %s given',
                __METHOD__,
                get_class($columnAliases)
            ));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRangeFunctionCall($this);
    }
}
