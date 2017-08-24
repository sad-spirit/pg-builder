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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\lists\SetTargetList,
    sad_spirit\pg_builder\SelectCommon,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a (column_name, ...) = (sub-select) construct in SET clause of UPDATE statement
 *
 * @property SetTargetList $columns
 * @property SelectCommon  $value
 */
class MultipleSetClause extends Node
{
    public function __construct(SetTargetList $columns, SelectCommon $value)
    {
        $this->setNamedProperty('columns', $columns);
        $this->setValue($value);
    }

    public function setValue(SelectCommon $value)
    {
        $this->setNamedProperty('value', $value);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkMultipleSetClause($this);
    }
}