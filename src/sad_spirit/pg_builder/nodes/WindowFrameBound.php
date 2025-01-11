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
    exceptions\InvalidArgumentException
};

/**
 * AST node generated for frame_bound grammar production in window specifications
 *
 * @property-read WindowFrameDirection  $direction
 * @property      ScalarExpression|null $value
 */
class WindowFrameBound extends GenericNode
{
    protected ?ScalarExpression $p_value = null;

    public function __construct(protected WindowFrameDirection $p_direction, ?ScalarExpression $value = null)
    {
        $this->generatePropertyNames();
        $this->setValue($value);
    }

    public function setValue(?ScalarExpression $value): void
    {
        if (
            null !== $value
            && !\in_array($this->p_direction, [WindowFrameDirection::PRECEDING, WindowFrameDirection::FOLLOWING])
        ) {
            throw new InvalidArgumentException("Value can only be set for PRECEDING or FOLLOWING direction");
        }
        $this->setProperty($this->p_value, $value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkWindowFrameBound($this);
    }
}
