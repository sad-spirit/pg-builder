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
    Parser,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    SetToDefault,
    lists\NonAssociativeList
};

/**
 * Represents a VALUES clause for INSERT actions of MERGE statement
 *
 * While this is quite similar to RowExpression, we don't extend that class as MergeValues cannot be used
 * in scalar expression contexts and thus shouldn't implement ScalarExpression
 *
 * @extends NonAssociativeList<
 *      ScalarExpression|SetToDefault,
 *      iterable<ScalarExpression|SetToDefault|string>|string,
 *      ScalarExpression|SetToDefault|string
 * >
 */
class MergeValues extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [
            ScalarExpression::class,
            SetToDefault::class
        ];
    }

    public function createElementFromString(string $sql): ScalarExpression|SetToDefault
    {
        return $this->getParserOrFail('VALUES element')->parseExpressionWithDefault($sql);
    }

    public static function createFromString(Parser $parser, string $sql)
    {
        return $parser->parseRowConstructorNoKeyword($sql);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeValues($this);
    }
}
