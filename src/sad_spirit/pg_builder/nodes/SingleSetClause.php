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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a single "column_name = expression" clause of UPDATE statement
 *
 * @property-read SetTargetElement              $column
 * @property      ScalarExpression|SetToDefault $value
 */
class SingleSetClause extends GenericNode
{
    public function __construct(SetTargetElement $column, $value)
    {
        $this->setNamedProperty('column', $column);
        $this->setValue($value);
    }

    public function setValue($value = null)
    {
        if (!($value instanceof ScalarExpression) && !($value instanceof SetToDefault)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a ScalarExpression or SetToDefault instance as value, %s given',
                __CLASS__,
                is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
        $this->setNamedProperty('value', $value);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSingleSetClause($this);
    }
}
