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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\nodes\lists\SetTargetList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a (column_name, ...) = (sub-select|row-expression) construct in SET clause of UPDATE statement
 *
 * Note that we allow any scalar expression as $value here (as does set_clause production in Postgres 10+ grammar),
 * however Parser will raise an error for anything that is not either a subselect or a row expression,
 * just like Postgres itself does.
 *
 * @property SetTargetList    $columns
 * @property ScalarExpression $value
 */
class MultipleSetClause extends GenericNode
{
    public function __construct(SetTargetList $columns, ScalarExpression $value)
    {
        $this->setNamedProperty('columns', $columns);
        $this->setValue($value);
    }

    public function setValue(ScalarExpression $value)
    {
        $this->setNamedProperty('value', $value);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkMultipleSetClause($this);
    }
}
