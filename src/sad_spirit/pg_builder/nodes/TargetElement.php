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

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a part of target list for a statement
 *
 * @property      ScalarExpression $expression
 * @property-read Identifier       $alias
 */
class TargetElement extends Node
{
    public function __construct(ScalarExpression $expression, Identifier $alias = null)
    {
        $this->setNamedProperty('expression', $expression);
        $this->setNamedProperty('alias', $alias);
    }

    public function setExpression(ScalarExpression $expression)
    {
        $this->setNamedProperty('expression', $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkTargetElement($this);
    }
}