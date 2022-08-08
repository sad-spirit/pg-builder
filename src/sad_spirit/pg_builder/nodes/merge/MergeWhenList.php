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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
 * @implements ElementParseable<MergeWhenClause>
 */
class MergeWhenList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [MergeWhenClause::class];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('WHEN [NOT] MATCHED clause')->parseMergeWhenClause($sql);
    }

    public static function createFromString(Parser $parser, string $sql)
    {
        return $parser->parseMergeWhenList($sql);
    }
}
