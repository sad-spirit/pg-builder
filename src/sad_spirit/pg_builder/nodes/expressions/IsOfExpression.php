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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\lists\TypeList,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an IS [NOT] OF expression
 *
 * Cannot be an OperatorExpression due to specific right operand
 *
 * @property      ScalarExpression $left
 * @property-read TypeList         $right
 * @property-read string           $operator
 */
class IsOfExpression extends Node implements ScalarExpression
{
    public function __construct(ScalarExpression $left, TypeList $right, $operator = 'is of')
    {
        if (!in_array($operator, array('is of', 'is not of'), true)) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for IS OF-style expression");
        }
        $this->setNamedProperty('left', $left);
        $this->setNamedProperty('right', $right);
        $this->props['operator'] = $operator;
    }

    public function setLeft(ScalarExpression $left)
    {
        $this->setNamedProperty('left', $left);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsOfExpression($this);
    }
}