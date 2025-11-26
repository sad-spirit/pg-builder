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
    nodes\ScalarExpression,
    ElementParseable
};
use sad_spirit\pg_builder\nodes\group\GroupByElement;

/**
 * List of elements appearing in GROUP BY clause
 *
 * The list can contain either expressions or special constructs like CUBE(), ROLLUP() and GROUPING SETS()
 *
 * @extends NonAssociativeList<
 *      ScalarExpression|GroupByElement,
 *      iterable<ScalarExpression|GroupByElement|string>|string,
 *      ScalarExpression|GroupByElement|string
 * >
 */
abstract class GroupByList extends NonAssociativeList implements ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [
            ScalarExpression::class,
            GroupByElement::class
        ];
    }

    public function createElementFromString(string $sql): ScalarExpression|GroupByElement
    {
        return $this->getParserOrFail('a list element')->parseGroupByElement($sql);
    }
}
