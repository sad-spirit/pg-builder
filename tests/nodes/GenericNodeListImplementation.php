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

namespace sad_spirit\pg_builder\tests\nodes;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\lists\GenericNodeList;

/**
 * A non-abstract subclass of GenericNodeList
 * @extends GenericNodeList<int|string, Node, iterable<Node>, Node>
 */
class GenericNodeListImplementation extends GenericNodeList
{
    protected function convertToArray($list, string $method): array
    {
        return [];
    }
}
