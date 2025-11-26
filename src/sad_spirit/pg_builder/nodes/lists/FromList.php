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
    Parseable,
    ElementParseable,
    Parser
};
use sad_spirit\pg_builder\nodes\range\FromElement;

/**
 * List of elements appearing in FROM clause
 *
 * @extends NonAssociativeList<
 *      FromElement,
 *      iterable<FromElement|string>|string,
 *      FromElement|string
 * >
 */
class FromList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [FromElement::class];
    }

    public function createElementFromString(string $sql): FromElement
    {
        return $this->getParserOrFail('a list element')->parseFromElement($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseFromList($sql);
    }
}
