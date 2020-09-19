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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a generic operator-like expression
 *
 * This is used for constructs of the form
 *  * <left argument> <operator>
 *  * <operator> <right argument>
 *  * <left argument> <operator> <right argument>
 * that do not have their own nodes. "Operators" here are not just MathOp / all_Op / etc.
 * productions from grammar, but constructs like e.g. 'IS DISTINCT FROM' as well.
 *
 * @property      ScalarExpression|null $left
 * @property      ScalarExpression|null $right
 * @property-read string                $operator
 */
class OperatorExpression extends Node implements ScalarExpression
{
    public function __construct($operator, ScalarExpression $left = null, ScalarExpression $right = null)
    {
        if (null === $left && null === $right) {
            throw new InvalidArgumentException('At least one operand is required for OperatorExpression');
        }
        $this->setNamedProperty('left', $left);
        $this->setNamedProperty('right', $right);
        $this->props['operator'] = (string)$operator;
    }

    public function setLeft(ScalarExpression $left = null)
    {
        if (null === $left && null === $this->props['right']) {
            throw new InvalidArgumentException('At least one operand is required for OperatorExpression');
        }
        $this->setNamedProperty('left', $left);
    }

    public function setRight(ScalarExpression $right = null)
    {
        if (null === $right && null === $this->props['left']) {
            throw new InvalidArgumentException('At least one operand is required for OperatorExpression');
        }
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOperatorExpression($this);
    }
}