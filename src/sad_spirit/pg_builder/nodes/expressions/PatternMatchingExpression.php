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
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing [NOT] LIKE | ILIKE | SIMILAR TO operators
 *
 * These cannot be represented by a standard Operator node as they can have a
 * trailing ESCAPE clause
 *
 * @property      ScalarExpression $argument
 * @property      ScalarExpression $pattern
 * @property      ScalarExpression $escape
 * @property-read string           $operator
 */
class PatternMatchingExpression extends Node implements ScalarExpression
{
    protected static $allowedOperators = array(
        'like'           => true,
        'not like'       => true,
        'ilike'          => true,
        'not ilike'      => true,
        'similar to'     => true,
        'not similar to' => true
    );

    public function __construct(
        ScalarExpression $argument, ScalarExpression $pattern, $operator = 'like', ScalarExpression $escape = null
    ) {
        if (!isset(self::$allowedOperators[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for pattern matching expression");
        }
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('pattern', $pattern);
        $this->setNamedProperty('escape', $escape);
        $this->props['operator'] = (string)$operator;
    }

    public function setArgument(ScalarExpression $argument)
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function setPattern(ScalarExpression $pattern)
    {
        $this->setNamedProperty('pattern', $pattern);
    }

    public function setEscape(ScalarExpression $escape = null)
    {
        $this->setNamedProperty('escape', $escape);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkPatternMatchingExpression($this);
    }
}