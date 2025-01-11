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

use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a named ':foo' query parameter
 */
class NamedParameter extends Parameter
{
    public function __construct(public readonly string $name)
    {
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkNamedParameter($this);
    }

    public function __serialize(): array
    {
        return [$this->name];
    }

    public function __unserialize(array $data): void
    {
        [$this->name] = $data;
    }
}
