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

namespace sad_spirit\pg_builder\tests\nodes;

use sad_spirit\pg_builder\{
    ElementParseable,
    Node,
    nodes\ScalarExpression,
    nodes\SetToDefault,
    Parseable,
    Parser
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * An implementation of NonAssociativeList, behaves similar to ExpressionList
 *
 * @extends NonAssociativeList<
 *     ScalarExpression|SetToDefault,
 *     iterable<ScalarExpression|SetToDefault|string>|string,
 *     ScalarExpression|SetToDefault|string
 * >
 */
class NonAssociativeListImplementation extends NonAssociativeList implements Parseable, ElementParseable
{
    public function __construct($list = null, private readonly ?Parser $parser = null)
    {
        parent::__construct($list);
    }

    public function getParser(): ?Parser
    {
        return $this->parser;
    }

    public function createElementFromString(string $sql): ScalarExpression|SetToDefault
    {
        return $this->getParserOrFail('a list element')->parseExpressionWithDefault($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return new self($parser->parseExpressionList($sql));
    }

    protected static function getAllowedElementClasses(): array
    {
        return [
            ScalarExpression::class,
            SetToDefault::class
        ];
    }
}
