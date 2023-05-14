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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
 * Cannot use range\FunctionCall instead as it has a lot more properties
 *
 * @psalm-property ColumnDefinitionList $columnAliases
 *
 * @property-read FunctionLike                            $function
 * @property      ColumnDefinitionList|ColumnDefinition[] $columnAliases
 */
class RowsFromElement extends GenericNode
{
    /** @var FunctionLike */
    protected $p_function;
    /** @var ColumnDefinitionList */
    protected $p_columnAliases;

    public function __construct(FunctionLike $function, ColumnDefinitionList $columnAliases = null)
    {
        $this->generatePropertyNames();

        $this->p_function = $function;
        $this->p_function->setParentNode($this);

        $this->p_columnAliases = $columnAliases ?? new ColumnDefinitionList();
        $this->p_columnAliases->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRowsFromElement($this);
    }
}
