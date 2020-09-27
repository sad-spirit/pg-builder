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

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\SelectCommon,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a subquery appearing in scalar expressions, possibly with a subquery operator applied
 *
 * @property      SelectCommon $query
 * @property-read string|null  $operator
 */
class SubselectExpression extends Node implements ScalarExpression
{
    protected static $allowedExpressions = [
        'exists' => true,
        'any'    => true,
        'all'    => true,
        'some'   => true,
        'array'  => true
        // "in" is served by InExpression
    ];

    public function __construct(SelectCommon $query, $operator = null)
    {
        if (null !== $operator) {
            $operator = (string)$operator;
            if (!isset(self::$allowedExpressions[$operator])) {
                throw new InvalidArgumentException("Unknown subquery operator '{$operator}'");
            }
        }
        $this->setQuery($query);
        $this->props['operator'] = $operator;
    }

    public function setQuery(SelectCommon $query)
    {
        $this->setNamedProperty('query', $query);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSubselectExpression($this);
    }
}