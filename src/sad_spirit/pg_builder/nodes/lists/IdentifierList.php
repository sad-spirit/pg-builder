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

use sad_spirit\pg_builder\{
    Node,
    nodes\Identifier,
    nodes\NonRecursiveNode
};

/**
 * List of identifiers, may appear in alias for FROM element
 *
 * @extends NonAssociativeList<Identifier, iterable<Identifier|string>, Identifier|string>
 */
class IdentifierList extends NonAssociativeList
{
    use NonRecursiveNode;

    protected static function getAllowedElementClasses(): array
    {
        return [Identifier::class];
    }

    protected function prepareListElement($value): Node
    {
        if (\is_string($value)) {
            $value = new Identifier($value);
        }
        return parent::prepareListElement($value);
    }
}
