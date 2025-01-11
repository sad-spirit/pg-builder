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

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkRowExpression($this);
    }
}
