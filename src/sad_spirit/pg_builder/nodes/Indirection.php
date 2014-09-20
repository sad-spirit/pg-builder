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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\nodes\lists\NonAssociativeList,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents an indirection (field selections or array subscripts) applied to an expression
 *
 * @property ScalarExpression $expression
 */
class Indirection extends NonAssociativeList implements ScalarExpression
{
    public function __construct($indirection, ScalarExpression $expression)
    {
        parent::__construct($indirection);
        $this->setNamedProperty('expression', $expression);
    }

    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof Identifier) && !($value instanceof ArrayIndexes) && !($value instanceof Star)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only Identifier, ArrayIndexes or Star instances, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public function setExpression(ScalarExpression $expression)
    {
        $this->setNamedProperty('expression', $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIndirection($this);
    }
}