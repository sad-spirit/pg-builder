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
    Parser,
    nodes\ScalarExpression,
    nodes\SetToDefault
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
        if (\is_iterable($value) && !$value instanceof RowExpression) {
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
