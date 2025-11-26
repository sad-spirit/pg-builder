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

namespace sad_spirit\pg_builder\nodes\merge;

use sad_spirit\pg_builder\{
    ElementParseable,
    Node,
    Parseable,
    Parser
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents a list of "WHEN [NOT] MATCHED ..." clauses of MERGE statement
 *
 * @extends NonAssociativeList<MergeWhenClause, iterable<MergeWhenClause|string>|string, MergeWhenClause|string>
 */
class MergeWhenList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [MergeWhenClause::class];
    }

    public function createElementFromString(string $sql): MergeWhenClause
    {
        return $this->getParserOrFail('WHEN [NOT] MATCHED clause')->parseMergeWhenClause($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseMergeWhenList($sql);
    }
}
