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

use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a '*' meaning "all fields"
 */
class Star extends GenericNode implements \Stringable
{
    use NonRecursiveNode;

    /**
     * This is only used for constructing exception messages
     */
    public function __toString(): string
    {
        return '*';
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkStar($this);
    }
}
