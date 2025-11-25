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

use sad_spirit\pg_builder\Node;

/**
 * Interface for nodes that are considered functions in Postgres' grammar
 *
 * Generally everything that is returned by `Parser::SpecialFunctionCall()` should implement this interface, as
 * these Nodes may be used instead of "normal" `FunctionCall` nodes in e.g. `range\FunctionCall`
 * and `range\RowsFromElement`
 */
interface FunctionLike extends Node
{
}
