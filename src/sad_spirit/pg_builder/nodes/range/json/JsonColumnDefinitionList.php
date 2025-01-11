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

namespace sad_spirit\pg_builder\nodes\range\json;

use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents a list of columns for json_table()
 *
 * @extends NonAssociativeList<JsonColumnDefinition, iterable<JsonColumnDefinition>, JsonColumnDefinition>
 */
class JsonColumnDefinitionList extends NonAssociativeList
{
    protected static function getAllowedElementClasses(): array
    {
        return [JsonColumnDefinition::class];
    }
}
