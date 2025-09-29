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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Lexer,
    Parser,
    nodes\WithClause
};

/**
 * Test for WithClause, checks that recursive property is properly set and reset
 */
class WithClauseTest extends TestCase
{
    private WithClause $withClause;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->withClause = new WithClause();
        $this->parser = new Parser(new Lexer());
    }

    public function testReplaceResetsRecursiveProperty(): void
    {
        $this::assertFalse($this->withClause->recursive);

        $this->withClause->replace($this->parser->parseWithClause(
            <<<QRY
with recursive bar (val) as (
    select 1 as val
    union all
    select val + 1
    from bar
)
QRY
        ));
        $this::assertTrue($this->withClause->recursive);

        $this->withClause->replace($this->parser->parseWithClause(
            <<<QRY
with baz (val) as (
    select 1 as val
) 
QRY
        ));
        $this::assertFalse($this->withClause->recursive);
    }

    public function testMergeSetsRecursiveProperty(): void
    {
        $this->withClause->merge($this->parser->parseWithClause(
            <<<QRY
with quux (xyzzy) as (
    select 2 as xyzzy
)
QRY
        ));
        $this::assertFalse($this->withClause->recursive);

        $this->withClause->merge($this->parser->parseWithClause(
            <<<QRY
with recursive bar (val) as (
    select 1 as val
    union all
    select val + 1
    from bar
)
QRY
        ));
        $this::assertTrue($this->withClause->recursive);

        $this->withClause->merge($this->parser->parseWithClause(
            <<<QRY
with baz (val) as (
    select 1 as val
) 
QRY
        ));
        $this::assertTrue($this->withClause->recursive);
    }
}
