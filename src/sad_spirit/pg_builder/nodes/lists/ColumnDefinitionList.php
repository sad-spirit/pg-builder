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

use sad_spirit\pg_builder\nodes\NonRecursiveNode;
use sad_spirit\pg_builder\nodes\range\ColumnDefinition;

/**
 * Column definition list, may appear in aliases for functions in FROM
 *
 * @extends NonAssociativeList<ColumnDefinition, iterable<ColumnDefinition>, ColumnDefinition>
 */
class ColumnDefinitionList extends NonAssociativeList
{
    use NonRecursiveNode;

    protected static function getAllowedElementClasses(): array
    {
        return [ColumnDefinition::class];
    }
}
