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
 * Interface for items of GraphPattern used by graph_table()
 *
 * Declared as an interface with (currently) a single implementation: `path_pattern_expression` production
 * in grammar hints at other possible implementations
 */
interface PathPattern extends Node
{
}
