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
    nodes\NonRecursiveNode,
    nodes\SetTargetElement,
    ElementParseable,
    Parseable,
    Parser
};

/**
 * Represents a list of SetTargetElements, used by INSERT and UPDATE statements
 *
 * @extends NonAssociativeList<SetTargetElement, iterable<SetTargetElement|string>|string, SetTargetElement|string>
 * @implements ElementParseable<SetTargetElement>
 */
class SetTargetList extends NonAssociativeList implements Parseable, ElementParseable
{
    use NonRecursiveNode;

    protected static function getAllowedElementClasses(): array
    {
        return [SetTargetElement::class];
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseInsertTargetList($sql);
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseSetTargetElement($sql);
    }
}
