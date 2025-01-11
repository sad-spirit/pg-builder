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
    Parseable,
    ElementParseable,
    Parser
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * List of XML namespaces appearing in XMLTABLE clause
 *
 * @extends NonAssociativeList<
 *     XmlNamespace,
 *     iterable<XmlNamespace|string>|string,
 *     XmlNamespace|string
 * >
 * @implements ElementParseable<XmlNamespace>
 */
class XmlNamespaceList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [XmlNamespace::class];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseXmlNamespace($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseXmlNamespaceList($sql);
    }
}
