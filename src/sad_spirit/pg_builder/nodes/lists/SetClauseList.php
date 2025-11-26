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
    ElementParseable,
    Node,
    Parseable,
    Parser,
    nodes\MultipleSetClause,
    nodes\SingleSetClause
};

/**
 * Represents SET clause of UPDATE statement
 *
 * @extends NonAssociativeList<
 *     SingleSetClause|MultipleSetClause,
 *     iterable<SingleSetClause|MultipleSetClause|string>|string,
 *     SingleSetClause|MultipleSetClause|string
 * >
 */
class SetClauseList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [
            SingleSetClause::class,
            MultipleSetClause::class
        ];
    }

    public function createElementFromString(string $sql): SingleSetClause|MultipleSetClause
    {
        return $this->getParserOrFail('a list element')->parseSetClause($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseSetClauseList($sql);
    }
}
