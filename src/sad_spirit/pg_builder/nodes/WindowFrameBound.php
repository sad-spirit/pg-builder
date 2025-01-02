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
    protected WindowFrameDirection $p_direction;
    protected ?ScalarExpression $p_value = null;

    public function __construct(WindowFrameDirection $direction, ?ScalarExpression $value = null)
    {
        $this->generatePropertyNames();
        $this->p_direction = $direction;
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

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowFrameBound($this);
    }
}
