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
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an expression from ORDER BY clause
 *
 * @property      ScalarExpression $expression
 * @property-read string           $direction
 * @property-read string           $nullsOrder
 * @property-read string           $operator
 */
class OrderByElement extends Node
{
    public function __construct(ScalarExpression $expression, $direction = null, $nullsOrder = null, $operator = null)
    {
        if (null !== $direction && !in_array($direction, array('asc', 'desc', 'using'), true)) {
            throw new InvalidArgumentException("Unknown sort direction '{$direction}'");
        } elseif ('using' === $direction && !$operator) {
            throw new InvalidArgumentException("Operator required for USING sort direction");
        }
        if (null !== $nullsOrder && !in_array($nullsOrder, array('first', 'last'), true)) {
            throw new InvalidArgumentException("Unknown nulls order '{$nullsOrder}'");
        }

        $this->setNamedProperty('expression', $expression);
        $this->props['direction']  = $direction;
        $this->props['nullsOrder'] = $nullsOrder;
        $this->props['operator']   = $operator;
    }

    public function setExpression(ScalarExpression $expression)
    {
        $this->setNamedProperty('expression', $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOrderByElement($this);
    }
}