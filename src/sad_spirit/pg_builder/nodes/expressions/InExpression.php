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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\SelectCommon,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a [NOT] IN expression
 *
 * Cannot be an OperatorExpression due to specific right operands
 *
 * @property      ScalarExpression            $left
 * @property      SelectCommon|ExpressionList $right
 * @property-read string                      $operator
 */
class InExpression extends Node implements ScalarExpression
{
    public function __construct(ScalarExpression $left, $right, $operator = 'in')
    {
        if (!in_array($operator, array('in', 'not in'), true)) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for IN-style expression");
        }
        $this->setRight($right);
        $this->setLeft($left);
        $this->props['operator'] = $operator;
    }

    public function setLeft(ScalarExpression $left)
    {
        $this->setNamedProperty('left', $left);
    }

    public function setRight($right)
    {
        if (!($right instanceof SelectCommon) && !($right instanceof ExpressionList)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an instance of either SelectCommon or ExpressionList as right operand, %s given',
                __CLASS__, is_object($right) ? 'object(' . get_class($right) . ')' : gettype($right)
            ));
        }
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkInExpression($this);
    }
}