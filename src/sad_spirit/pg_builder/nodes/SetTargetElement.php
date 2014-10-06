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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\nodes\lists\NonAssociativeList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a part of a targer list for INSERT or UPDATE statements
 *
 * Possible indirection is represented by array offsets. Unlike normal Indirection nodes,
 * Star indirection is not possible as Postgres does not allow it:
 * 'ERROR:  row expansion via "*" is not supported here'
 *
 * @property-read Identifier                    $name
 * @property      ScalarExpression|SetToDefault $value
 */
class SetTargetElement extends NonAssociativeList
{
    public function __construct(Identifier $name, array $indirection = array(), $value = null)
    {
        parent::__construct($indirection);
        $this->setValue($value);
        $this->setNamedProperty('name', $name);
    }

    public function setValue($value = null)
    {
        if (null !== $value && !($value instanceof ScalarExpression) && !($value instanceof SetToDefault)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a ScalarExpression or SetToDefault instance as value, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
        $this->setNamedProperty('value', $value);
    }

    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof Identifier) && !($value instanceof ArrayIndexes)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only Identifier or ArrayIndexes instances, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSetTargetElement($this);
    }
}