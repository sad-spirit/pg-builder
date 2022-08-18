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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
     * @param Parser $parser
     * @param string $sql
     * @return self
     * @throws exceptions\SyntaxException
     */
    public static function createFromString(Parser $parser, string $sql);
}
