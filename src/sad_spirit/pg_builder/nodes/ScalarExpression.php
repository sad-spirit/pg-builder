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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Interface for Nodes that can appear as parts of expression
 */
interface ScalarExpression
{
    /**
     * Double-dispatch method supposed to call the relevant method of TreeWalker
     *
     * This is mostly here to make typehints of ScalarExpression work properly, all the
     * classes implementing the interface are instances of Node anyway and thus contain
     * this method
     *
     * @param TreeWalker $walker
     * @return mixed
     */
    public function dispatch(TreeWalker $walker);

    /**
     * Returns the node containing current one
     *
     * This is mostly here to make typehints of ScalarExpression work properly, all the
     * classes implementing the interface are instances of Node anyway and thus contain
     * this method
     *
     * @return Node|null
     */
    public function getParentNode();
}