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
 * @implements ElementParseable<LockingElement>
 */
class LockList extends NonAssociativeList implements Parseable, ElementParseable
{
    use NonRecursiveNode;

    protected static function getAllowedElementClasses(): array
    {
        return [LockingElement::class];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseLockingElement($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseLockingList($sql);
    }
}
