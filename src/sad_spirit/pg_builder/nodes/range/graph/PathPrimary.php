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

namespace sad_spirit\pg_builder\nodes\range\graph;

use sad_spirit\pg_builder\Node;

/**
 * Interface for elements appearing in path patterns
 *
 * Implementations are `ElementPattern` representing vertices and edges and NestedPattern representing a parenthesized
 * path pattern. The latter is parsed by Postgres but not currently supported.
 */
interface PathPrimary extends Node
{
}
