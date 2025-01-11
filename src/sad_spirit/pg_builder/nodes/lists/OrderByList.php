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
    nodes\OrderByElement,
    Parseable,
    ElementParseable,
    Parser
};

/**
 * List of elements appearing in ORDER BY clause
 *
 * @extends NonAssociativeList<
 *      OrderByElement,
 *      iterable<OrderByElement|string>|string,
 *      OrderByElement|string
 * >
 * @implements ElementParseable<OrderByElement>
 */
class OrderByList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [OrderByElement::class];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseOrderByElement($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseOrderByList($sql);
    }
}
