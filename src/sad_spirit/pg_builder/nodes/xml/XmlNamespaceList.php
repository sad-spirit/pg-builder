<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
