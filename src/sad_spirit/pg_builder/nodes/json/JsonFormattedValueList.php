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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents a list of values for JSON
 *
 * @extends NonAssociativeList<JsonFormattedValue, iterable<JsonFormattedValue>, JsonFormattedValue>
 */
class JsonFormattedValueList extends NonAssociativeList
{
    protected static function getAllowedElementClasses(): array
    {
        return [JsonFormattedValue::class];
    }
}
