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
 * @implements ElementParseable<ScalarExpression|SetToDefault>
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

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('VALUES element')->parseExpressionWithDefault($sql);
    }

    public static function createFromString(Parser $parser, string $sql)
    {
        return $parser->parseRowConstructorNoKeyword($sql);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkMergeValues($this);
    }
}
