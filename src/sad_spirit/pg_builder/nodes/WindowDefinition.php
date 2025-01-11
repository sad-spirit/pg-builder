<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
    protected Identifier|null $p_name = null;
    protected Identifier|null $p_refName = null;
    protected ExpressionList $p_partition;
    protected OrderByList $p_order;
    protected WindowFrameClause|null $p_frame = null;

    public function __construct(
        ?Identifier $refName = null,
        ?ExpressionList $partition = null,
        ?OrderByList $order = null,
        ?WindowFrameClause $frame = null
    ) {
        $this->generatePropertyNames();

        $this->p_partition = $partition ?? new ExpressionList();
        $this->p_partition->setParentNode($this);

        $this->p_order = $order ?? new OrderByList();
        $this->p_order->setParentNode($this);

        if (null !== $refName) {
            $this->p_refName = $refName;
            $this->p_refName->setParentNode($this);
        }

        if (null !== $frame) {
            $this->p_frame = $frame;
            $this->p_frame->setParentNode($this);
        }
    }

    public function setName(?Identifier $name): void
    {
        $this->setProperty($this->p_name, $name);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkWindowDefinition($this);
    }
}
