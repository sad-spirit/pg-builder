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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
    /** @var WithClause  */
    private $withClause;

    protected function setUp(): void
    {
        // We need a parser available for the tests
        $this->withClause = (new StatementFactory())->insert(new QualifiedName('foo'))->with;
    }

    public function testReplaceResetsRecursiveProperty(): void
    {
        $this::assertFalse($this->withClause->recursive);

        $this->withClause->replace(<<<QRY
with recursive bar (val) as (
    select 1 as val
    union all
    select val + 1
    from bar
)
QRY
        );
        $this::assertTrue($this->withClause->recursive);

        $this->withClause->replace(<<<QRY
with baz (val) as (
    select 1 as val
) 
QRY
        );
        $this::assertFalse($this->withClause->recursive);
    }

    public function testMergeSetsRecursiveProperty(): void
    {
        $this->withClause->merge(<<<QRY
with quux (xyzzy) as (
    select 2 as xyzzy
)
QRY
        );
        $this::assertFalse($this->withClause->recursive);

        $this->withClause->merge(<<<QRY
with recursive bar (val) as (
    select 1 as val
    union all
    select val + 1
    from bar
)
QRY
        );
        $this::assertTrue($this->withClause->recursive);

        $this->withClause->merge(<<<QRY
with baz (val) as (
    select 1 as val
) 
QRY
        );
        $this::assertTrue($this->withClause->recursive);
    }
}
