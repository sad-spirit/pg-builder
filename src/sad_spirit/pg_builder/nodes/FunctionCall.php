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
    sad_spirit\pg_builder\nodes\lists\OrderByList,
    sad_spirit\pg_builder\nodes\lists\FunctionArgumentList,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a function call (func_application or func_expr_common_subexpr)
 *
 * This will be wrapped by either expressions\FunctionExpression for scalar
 * contexts where window functions are possible, or by range\FunctionCall
 * for functions in FROM
 *
 * @property-read string|QualifiedName      $name
 * @property      FunctionArgumentList|Star $arguments
 * @property-read bool                      $distinct
 * @property-read bool                      $variadic
 * @property      OrderByList               $order
 */
class FunctionCall extends Node
{
    public function __construct(
        $funcName, $arguments = null, $distinct = false, $variadic = false, OrderByList $orderBy = null
    ) {
        if (!is_string($funcName) && !($funcName instanceof QualifiedName)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a string or a QualifiedName as function name, %s given',
                __CLASS__, is_object($funcName) ? 'object(' . get_class($funcName) . ')' : gettype($funcName)
            ));
        }
        if (null !== $arguments && !($arguments instanceof FunctionArgumentList) && !($arguments instanceof Star)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of either Star or FunctionArgumentList for function arguments, %s given',
                __CLASS__, is_object($arguments) ? 'object(' . get_class($arguments) . ')' : gettype($arguments)
            ));
        }

        $this->setNamedProperty('name', $funcName);
        $this->setNamedProperty('arguments', $arguments ?: new FunctionArgumentList(array()));
        $this->props['distinct'] = (bool)$distinct;
        $this->props['variadic'] = (bool)$variadic;
        $this->setNamedProperty('order', $orderBy ?: new OrderByList());
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkFunctionCall($this);
    }
}