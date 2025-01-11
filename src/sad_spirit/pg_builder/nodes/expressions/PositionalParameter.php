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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a positional '$1' query parameter
 */
class PositionalParameter extends Parameter
{
    public readonly int $position;

    public function __construct(int $position)
    {
        if (0 >= $position) {
            throw new InvalidArgumentException("Position should be positive");
        }
        $this->position = $position;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkPositionalParameter($this);
    }

    public function __serialize(): array
    {
        return [$this->position];
    }

    public function __unserialize(array $data): void
    {
        [$this->position] = $data;
    }
}
