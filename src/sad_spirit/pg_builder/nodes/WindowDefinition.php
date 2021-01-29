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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\nodes\lists\ExpressionList;
use sad_spirit\pg_builder\nodes\lists\OrderByList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a window definition (for function calls with OVER and for WINDOW clause)
 *
 * @property      Identifier|null        $name
 * @property-read Identifier|null        $refName
 * @property      ExpressionList         $partition
 * @property      OrderByList            $order
 * @property-read WindowFrameClause|null $frame
 */
class WindowDefinition extends GenericNode
{
    /** @var Identifier|null */
    protected $p_name;
    /** @var Identifier|null */
    protected $p_refName;
    /** @var ExpressionList */
    protected $p_partition;
    /** @var OrderByList */
    protected $p_order;
    /** @var WindowFrameClause|null */
    protected $p_frame;

    public function __construct(
        Identifier $refName = null,
        ExpressionList $partition = null,
        OrderByList $order = null,
        WindowFrameClause $frame = null
    ) {
        $this->generatePropertyNames();
        $this->p_name = null;
        $this->setProperty($this->p_refName, $refName);
        $this->setProperty($this->p_partition, $partition ?? new ExpressionList());
        $this->setProperty($this->p_order, $order ?? new OrderByList());
        $this->setProperty($this->p_frame, $frame);
    }

    public function setName(Identifier $name = null): void
    {
        $this->setProperty($this->p_name, $name);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowDefinition($this);
    }
}
