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

use sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a GROUPING(...) expression
 */
class GroupingExpression extends ExpressionList implements ScalarExpression
{
    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkGroupingExpression($this);
    }
}