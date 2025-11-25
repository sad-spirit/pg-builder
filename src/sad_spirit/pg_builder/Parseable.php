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
 * Interface for Nodes that can be created from an SQL string representation
 */
interface Parseable
{
    /**
     * Parses the SQL returning an AST for the relevant element
     *
     * @return self
     * @throws exceptions\SyntaxException
     */
    public static function createFromString(Parser $parser, string $sql);
}
