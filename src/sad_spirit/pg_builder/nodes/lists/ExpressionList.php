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
    Parseable,
    ElementParseable,
    Parser
};

/**
 * List of scalar expressions, may appear e.g. in "foo IN (...)" constructs
 *
 * @extends NonAssociativeList<
 *      ScalarExpression,
 *      iterable<ScalarExpression|string>|string,
 *      ScalarExpression|string
 * >
 */
class ExpressionList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [ScalarExpression::class];
    }

    public function createElementFromString(string $sql): ScalarExpression
    {
        return $this->getParserOrFail('a list element')->parseExpression($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseExpressionList($sql);
    }
}
