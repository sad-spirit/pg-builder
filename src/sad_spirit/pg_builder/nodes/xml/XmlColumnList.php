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

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\{
    Node,
    ElementParseable,
    Parseable,
    Parser
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * List of column definitions appearing in XMLTABLE clause
 *
 * @extends NonAssociativeList<
 *     XmlColumnDefinition,
 *     iterable<XmlColumnDefinition|string>|string,
 *     XmlColumnDefinition|string
 * >
 * @implements ElementParseable<XmlColumnDefinition>
 */
class XmlColumnList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [XmlColumnDefinition::class];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseXmlColumnDefinition($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseXmlColumnList($sql);
    }
}
