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

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\nodes\range\RowsFromElement;

/**
 * List of elements appearing in ROWS FROM(...) construct
 *
 * @extends NonAssociativeList<RowsFromElement, iterable<RowsFromElement>, RowsFromElement>
 */
class RowsFromList extends NonAssociativeList
{
    protected static function getAllowedElementClasses(): array
    {
        return [RowsFromElement::class];
    }
}
