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
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    ReturningClause,
    Star,
    TargetElement
};

/**
 * Test for ReturningClause, checks that OLD and NEW aliases are properly processed
 */
class ReturningClauseTest extends TestCase
{
    public function testReplaceResetsAliases(): void
    {
        $returningClause = new ReturningClause(
            [new Star()],
            null,
            new Identifier('newer')
        );

        $returningClause->replace(new ReturningClause(
            [new TargetElement(new ColumnReference('foo'))],
            new Identifier('older'),
            null
        ));

        $this::assertSame('older', $returningClause->oldAlias?->value);
        $this::assertNull($returningClause->newAlias);

        $returningClause->replace([
            new TargetElement(new ColumnReference('bar')),
        ]);
        $this::assertNull($returningClause->oldAlias);
        $this::assertNull($returningClause->newAlias);
    }

    public function testMergeAddsAliases(): void
    {
        $returningClause = new ReturningClause([new Star()]);

        $returningClause->merge(new ReturningClause([], new Identifier('older')));
        $returningClause->merge(new ReturningClause([], null, new Identifier('newer')));

        $this::assertSame('older', $returningClause->oldAlias?->value);
        $this::assertSame('newer', $returningClause->newAlias?->value);
    }

    public function testDisallowMultipleAliasesInMerge(): void
    {
        $returningClause = new ReturningClause([new Star()], new Identifier('older'));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('multiple times');
        $returningClause->merge(new ReturningClause([], new Identifier('oldest')));
    }
}
