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

use sad_spirit\pg_builder\nodes\FunctionCall,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\nodes\WindowDefinition,
    sad_spirit\pg_builder\nodes\lists\OrderByList,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a function call in scalar context (func_expr in grammar)
 *
 * @property-read bool                  $withinGroup
 * @property-read ScalarExpression|null $filter
 * @property-read WindowDefinition|null $over
 */
class FunctionExpression extends FunctionCall implements ScalarExpression
{
    public function __construct(
        $funcName, $arguments = null, $distinct = false, $variadic = false,
        OrderByList $orderBy = null, $withinGroup = false, ScalarExpression $filter = null,
        WindowDefinition $over = null
    ) {
        parent::__construct($funcName, $arguments, $distinct, $variadic, $orderBy);
        $this->setNamedProperty('withinGroup', (bool)$withinGroup);
        $this->setNamedProperty('filter', $filter);
        $this->setNamedProperty('over', $over);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkFunctionExpression($this);
    }
}