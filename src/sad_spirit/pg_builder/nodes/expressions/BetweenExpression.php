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

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing [NOT] BETWEEN expression
 *
 * @property      ScalarExpression $argument
 * @property      ScalarExpression $left
 * @property      ScalarExpression $right
 * @property-read string           $operator
 */
class BetweenExpression extends Node implements ScalarExpression
{
    protected static $allowedOperators = [
        'between symmetric'      => true,
        'between asymmetric'     => true,
        'not between symmetric'  => true,
        'not between asymmetric' => true,
        'between'                => true,
        'not between'            => true
    ];

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $left,
        ScalarExpression $right,
        $operator = 'between'
    ) {
        if (!isset(self::$allowedOperators[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for BETWEEN-style expression");
        }
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('left', $left);
        $this->setNamedProperty('right', $right);
        $this->props['operator'] = (string)$operator;
    }

    public function setArgument(ScalarExpression $argument)
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function setLeft(ScalarExpression $left)
    {
        $this->setNamedProperty('left', $left);
    }

    public function setRight(ScalarExpression $right)
    {
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkBetweenExpression($this);
    }
}
