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

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionCall,
    ScalarExpression,
    WindowDefinition,
    lists\OrderByList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a function call in scalar context (func_expr in grammar)
 *
 * @property-read bool                  $withinGroup
 * @property-read ScalarExpression|null $filter
 * @property-read WindowDefinition|null $over
 */
class FunctionExpression extends FunctionCall implements ScalarExpression
{
    use ExpressionAtom;

    /** @var bool */
    protected $p_withinGroup;
    /** @var ScalarExpression|null */
    protected $p_filter;
    /** @var WindowDefinition|null */
    protected $p_over;

    public function __construct(
        $funcName,
        $arguments = null,
        bool $distinct = false,
        bool $variadic = false,
        OrderByList $orderBy = null,
        bool $withinGroup = false,
        ScalarExpression $filter = null,
        WindowDefinition $over = null
    ) {
        parent::__construct($funcName, $arguments, $distinct, $variadic, $orderBy);
        $this->p_withinGroup = $withinGroup;
        $this->setProperty($this->p_filter, $filter);
        $this->setProperty($this->p_over, $over);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkFunctionExpression($this);
    }
}
