<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\{
    Node,
    Parseable,
    ElementParseable,
    Parser
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    SetToDefault
};
use sad_spirit\pg_builder\nodes\expressions\RowExpression;

/**
 * A list of row expressions, base for VALUES statement
 *
 * @extends NonAssociativeList<
 *     RowExpression,
 *     iterable<RowExpression|string|iterable<ScalarExpression|SetToDefault|string>>|string,
 *     RowExpression|string|iterable<ScalarExpression|SetToDefault|string>
 * >
 * @implements ElementParseable<RowExpression>
 */
class RowList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [RowExpression::class];
    }

    protected function prepareListElement($value): Node
    {
        if (is_iterable($value) && !$value instanceof RowExpression) {
            $value = new RowExpression($value);
        }
        return parent::prepareListElement($value);
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseRowConstructorNoKeyword($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseRowList($sql);
    }
}
