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
    nodes\WindowDefinition,
    Parseable,
    ElementParseable,
    Parser
};

/**
 * List of window definitions (WINDOW clause of SELECT)
 *
 * @extends NonAssociativeList<
 *      WindowDefinition,
 *      iterable<WindowDefinition|string>|string,
 *      WindowDefinition|string
 * >
 */
class WindowList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [WindowDefinition::class];
    }

    public function createElementFromString(string $sql): WindowDefinition
    {
        return $this->getParserOrFail('a list element')->parseWindowDefinition($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseWindowList($sql);
    }
}
