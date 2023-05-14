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

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\{
    Node,
    nodes\ScalarExpression,
    Parseable,
    ElementParseable,
    Parser
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
 * @implements ElementParseable<ScalarExpression|GroupByElement>
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

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseGroupByElement($sql);
    }
}
