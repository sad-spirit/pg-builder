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

use sad_spirit\pg_builder\nodes\lists\ExpressionList;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\Parser;

/**
 * Represents a ROW(...) constructor expression
 */
class RowExpression extends ExpressionList implements ScalarExpression
{
    /**
     * SetToDefault nodes should be allowed in RowExpressions
     * @var bool
     */
    protected $allowDefault = true;

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRowExpression($this);
    }

    public static function createFromString(Parser $parser, $sql)
    {
        return $parser->parseRowConstructor($sql);
    }
}
