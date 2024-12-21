<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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
 * @psalm-property ExpressionList $partition
 * @psalm-property OrderByList    $order
 *
 * @property      Identifier|null                   $name
 * @property-read Identifier|null                   $refName
 * @property      ExpressionList|ScalarExpression[] $partition
 * @property      OrderByList|OrderByElement[]      $order
 * @property-read WindowFrameClause|null            $frame
 */
class WindowDefinition extends GenericNode
{
    /** @var Identifier|null */
    protected $p_name = null;
    /** @var Identifier|null */
    protected $p_refName = null;
    /** @var ExpressionList */
    protected $p_partition;
    /** @var OrderByList */
    protected $p_order;
    /** @var WindowFrameClause|null */
    protected $p_frame = null;

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

    public function setName(?Identifier $name = null): void
    {
        $this->setProperty($this->p_name, $name);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowDefinition($this);
    }
}
