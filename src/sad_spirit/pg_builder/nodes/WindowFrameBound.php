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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node generated for frame_bound grammar production in window specifications
 *
 * @property-read string                $direction
 * @property      ScalarExpression|null $value
 */
class WindowFrameBound extends Node
{
    public function __construct($direction, ScalarExpression $value = null)
    {
        if (!in_array($direction, array('preceding', 'following', 'current row'), true)) {
            throw new InvalidArgumentException("Unknown window frame direction '{$direction}'");
        }
        $this->props['direction'] = $direction;
        $this->setValue($value);
    }

    public function setValue(ScalarExpression $value = null)
    {
        if (!is_null($value) && !in_array($this->props['direction'], array('preceding', 'following'))) {
            throw new InvalidArgumentException("Value can only be set for PRECEDING or FOLLOWING direction");
        }
        $this->setNamedProperty('value', $value);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowFrameBound($this);
    }
}