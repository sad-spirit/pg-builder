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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\FunctionCall as BaseFunctionCall,
    sad_spirit\pg_builder\nodes\lists\ColumnDefinitionList,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a function call inside ROWS FROM construct
 *
 * Cannot use range\FunctionCall instead as it has a lot more properties
 *
 * @property-read BaseFunctionCall     $function
 * @property      ColumnDefinitionList $columnAliases
 */
class RowsFromElement extends Node
{
    public function __construct(BaseFunctionCall $function, ColumnDefinitionList $columnAliases = null)
    {
        $this->setNamedProperty('function', $function);
        $this->setNamedProperty('columnAliases', $columnAliases);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRowsFromElement($this);
    }
}