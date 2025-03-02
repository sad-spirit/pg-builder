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
    nodes\QualifiedName,
    nodes\WithClause,
    StatementFactory
};

/**
 * Test for WithClause, checks that recursive property is properly set and reset
 */
class WithClauseTest extends TestCase
{
    private WithClause $withClause;

    protected function setUp(): void
    {
        // We need a parser available for the tests
        $this->withClause = (new StatementFactory())->insert(new QualifiedName('foo'))->with;
    }

    public function testReplaceResetsRecursiveProperty(): void
    {
        $this::assertFalse($this->withClause->recursive);

        $this->withClause->replace(
            <<<QRY
with recursive bar (val) as (
    select 1 as val
    union all
    select val + 1
    from bar
)
QRY
        );
        $this::assertTrue($this->withClause->recursive);

        $this->withClause->replace(
            <<<QRY
with baz (val) as (
    select 1 as val
) 
QRY
        );
        $this::assertFalse($this->withClause->recursive);
    }

    public function testMergeSetsRecursiveProperty(): void
    {
        $this->withClause->merge(
            <<<QRY
with quux (xyzzy) as (
    select 2 as xyzzy
)
QRY
        );
        $this::assertFalse($this->withClause->recursive);

        $this->withClause->merge(
            <<<QRY
with recursive bar (val) as (
    select 1 as val
    union all
    select val + 1
    from bar
)
QRY
        );
        $this::assertTrue($this->withClause->recursive);

        $this->withClause->merge(
            <<<QRY
with baz (val) as (
    select 1 as val
) 
QRY
        );
        $this::assertTrue($this->withClause->recursive);
    }
}
