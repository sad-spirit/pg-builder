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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\nodes\lists\NonAssociativeList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents an array constructed from a list of values ARRAY[...]
 */
class ArrayExpression extends NonAssociativeList implements ScalarExpression
{
    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (is_array($value) || (($value instanceof \Traversable) && !($value instanceof self))) {
            $value = new self($value);
        }
        if (!($value instanceof ScalarExpression) && !($value instanceof self)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of ScalarExpression or nested instances of ArrayExpression, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkArrayExpression($this);
    }
}
