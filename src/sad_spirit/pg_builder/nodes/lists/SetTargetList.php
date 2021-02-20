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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
