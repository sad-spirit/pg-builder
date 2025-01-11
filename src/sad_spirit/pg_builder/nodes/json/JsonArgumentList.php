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
 * Represents the "PASSING ..." clause of various JSON expressions
 *
 * @extends NonAssociativeList<JsonArgument, iterable<JsonArgument>, JsonArgument>
 */
class JsonArgumentList extends NonAssociativeList
{
    protected static function getAllowedElementClasses(): array
    {
        return [JsonArgument::class];
    }
}
