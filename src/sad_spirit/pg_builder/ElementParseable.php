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

namespace sad_spirit\pg_builder;

/**
 * Interface for NodeLists that know how to parse SQL for their elements
 */
interface ElementParseable
{
    /**
     * Parses the SQL for a list element, returning the AST for it
     *
     * @throws exceptions\SyntaxException
     */
    public function createElementFromString(string $sql): Node;
}
