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

use sad_spirit\pg_builder\nodes\expressions\Constant;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\nodes\NonRecursiveNode;

/**
 * List of type modifiers
 *
 * @extends NonAssociativeList<Constant|Identifier, iterable<Constant|Identifier>, Constant|Identifier>
 */
class TypeModifierList extends NonAssociativeList
{
    use NonRecursiveNode;

    protected static function getAllowedElementClasses(): array
    {
        return [
            Constant::class,
            Identifier::class
        ];
    }
}
