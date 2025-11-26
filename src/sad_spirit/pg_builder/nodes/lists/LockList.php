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
    nodes\LockingElement,
    nodes\NonRecursiveNode,
    Parseable,
    ElementParseable,
    Parser
};

/**
 * List of locking clauses attached to SELECT
 *
 * @extends NonAssociativeList<
 *      LockingElement,
 *      iterable<LockingElement|string>|string,
 *      LockingElement|string
 * >
 */
class LockList extends NonAssociativeList implements Parseable, ElementParseable
{
    use NonRecursiveNode;

    protected static function getAllowedElementClasses(): array
    {
        return [LockingElement::class];
    }

    public function createElementFromString(string $sql): LockingElement
    {
        return $this->getParserOrFail('a list element')->parseLockingElement($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseLockingList($sql);
    }
}
