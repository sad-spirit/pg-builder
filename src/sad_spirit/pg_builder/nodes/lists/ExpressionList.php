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
    nodes\ScalarExpression,
    Parseable,
    ElementParseable,
    Parser
};

/**
 * List of scalar expressions, may appear e.g. in row constructors
 *
 * In Postgres 10+ DEFAULT keyword is allowed by grammar in any expression (a_expr production),
 * however it will later raise an error except when appearing as a top-level expression
 *  - in row of VALUES clause *if* that clause is attached to INSERT
 *  - in row expression being assigned to multiple columns in UPDATE
 *
 * Therefore we don't make SetToDefault node an implementation of ScalarExpression and only allow
 * it on the top level of RowExpression. Since the latter extends ExpressionList, the knobs to
 * allow SetToDefault are defined here.
 */
class ExpressionList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [ScalarExpression::class];
    }

    public function createElementFromString(string $sql): Node
    {
        $parser = $this->getParserOrFail('a list element');
        return count(static::getAllowedElementClasses()) > 1
            ? $parser->parseExpressionWithDefault($sql)
            : $parser->parseExpression($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseExpressionList($sql);
    }
}
