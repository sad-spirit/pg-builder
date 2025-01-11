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

use sad_spirit\pg_builder\{
    TreeWalker,
    enums\WindowFrameDirection,
    enums\WindowFrameExclusion,
    enums\WindowFrameMode,
    exceptions\InvalidArgumentException
};

/**
 * AST node representing the window frame (opt_frame_clause production in grammar)
 *
 * @property-read WindowFrameMode           $type
 * @property-read WindowFrameBound          $start
 * @property-read WindowFrameBound|null     $end
 * @property-read WindowFrameExclusion|null $exclusion
 */
class WindowFrameClause extends GenericNode
{
    protected WindowFrameBound $p_start;
    protected ?WindowFrameBound $p_end = null;

    public function __construct(
        protected WindowFrameMode $p_type,
        WindowFrameBound $start,
        ?WindowFrameBound $end = null,
        protected ?WindowFrameExclusion $p_exclusion = null
    ) {
        $this->generatePropertyNames();

        // like in frame_extent production in gram.y, reject invalid frame cases
        if (WindowFrameDirection::FOLLOWING === $start->direction && !$start->value) {
            throw new InvalidArgumentException('Frame start cannot be UNBOUNDED FOLLOWING');
        }
        if (null === $end) {
            if (WindowFrameDirection::FOLLOWING === $start->direction && $start->value) {
                throw new InvalidArgumentException('Frame starting from following row cannot end with current row');
            }

        } else {
            if (WindowFrameDirection::PRECEDING === $end->direction && !$end->value) {
                throw new InvalidArgumentException("Frame end cannot be UNBOUNDED PRECEDING");
            }
            if (
                WindowFrameDirection::CURRENT_ROW === $start->direction
                && WindowFrameDirection::PRECEDING === $end->direction
            ) {
                throw new InvalidArgumentException("Frame starting from current row cannot have preceding rows");
            }
            if (
                WindowFrameDirection::FOLLOWING === $start->direction
                && \in_array($end->direction, [WindowFrameDirection::CURRENT_ROW, WindowFrameDirection::PRECEDING])
            ) {
                throw new InvalidArgumentException("Frame starting from following row cannot have preceding rows");
            }
        }

        $this->p_start = $start;
        $this->p_start->setParentNode($this);

        if (null !== $end) {
            $this->p_end = $end;
            $this->p_end->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkWindowFrameClause($this);
    }
}
