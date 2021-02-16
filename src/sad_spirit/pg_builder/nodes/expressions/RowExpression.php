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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    ElementParseable,
    Node,
    Parseable,
    TreeWalker,
    Parser,
    nodes\ExpressionAtom,
    nodes\ScalarExpression,
    nodes\SetToDefault
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents a ROW(...) constructor expression
 *
 * In Postgres 10+ DEFAULT keyword is allowed by grammar in any expression (a_expr production),
 * however it will later cause an error except when appearing as a top-level expression
 *  - in row of VALUES clause *if* that clause is attached to INSERT
 *  - in row expression being assigned to multiple columns in UPDATE
 *
 * Therefore we don't make SetToDefault node an implementation of ScalarExpression and only allow
 * it on the top level of RowExpression.
 *
 * @extends NonAssociativeList<
 *      ScalarExpression|SetToDefault,
 *      iterable<ScalarExpression|SetToDefault|string>|string,
 *      ScalarExpression|SetToDefault|string
 * >
 * @implements ElementParseable<ScalarExpression|SetToDefault>
 */
class RowExpression extends NonAssociativeList implements Parseable, ElementParseable, ScalarExpression
{
    use ExpressionAtom;

    protected static function getAllowedElementClasses(): array
    {
        return [
            ScalarExpression::class,
            SetToDefault::class
        ];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseExpressionWithDefault($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseRowConstructor($sql);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRowExpression($this);
    }
}
