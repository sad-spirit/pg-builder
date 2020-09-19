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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\lists\OrderByList,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a window definition (for function calls with OVER and for WINDOW clause)
 *
 * @property      Identifier|null        $name
 * @property-read Identifier|null        $refName
 * @property      ExpressionList         $partition
 * @property      OrderByList            $order
 * @property-read WindowFrameClause|null $frame
 */
class WindowDefinition extends Node
{
    public function __construct(
        Identifier $refName = null, ExpressionList $partition = null, OrderByList $order = null,
        WindowFrameClause $frame = null
    ) {
        $this->props['name']       = null;
        $this->setNamedProperty('refName', $refName);
        $this->setNamedProperty('partition', $partition ?: new ExpressionList());
        $this->setNamedProperty('order', $order ?: new OrderByList());
        $this->setNamedProperty('frame', $frame);
    }

    public function setName(Identifier $name = null)
    {
        $this->setNamedProperty('name', $name);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowDefinition($this);
    }
}