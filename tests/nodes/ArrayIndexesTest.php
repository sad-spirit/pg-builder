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
use sad_spirit\pg_builder\nodes\ArrayIndexes;
use sad_spirit\pg_builder\nodes\expressions\NumericConstant;

/**
 * Tests the specific behaviour of ArrayIndexes node
 */
class ArrayIndexesTest extends TestCase
{
    public function testAllowsSliceWithoutBounds(): void
    {
        $indexes = new ArrayIndexes(null, null, true);
        $this::assertNull($indexes->lower);
        $this::assertNull($indexes->upper);
    }

    public function testCannotUseTheSameNodeForBounds(): void
    {
        $one = new NumericConstant('1');

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Cannot use the same Node');
        new ArrayIndexes($one, $one, true);
    }

    public function testRequireUpperBoundForNonSlice(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Upper bound is required');
        new ArrayIndexes(new NumericConstant('1'));
    }

    public function testCannotRemoveUpperBoundForNonSlice(): void
    {
        $indexes = new ArrayIndexes(null, new NumericConstant('2'));
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Upper bound is required');
        $indexes->upper = null;
    }

    public function testDisallowLowerBoundForNonSlice(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Lower bound should be omitted');
        new ArrayIndexes(new NumericConstant('1'), new NumericConstant('2'));
    }

    public function testCannotSetLowerBoundForNonSlice(): void
    {
        $indexes = new ArrayIndexes(null, new NumericConstant('2'));
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Lower bound should be omitted');
        $indexes->lower = new NumericConstant('1');
    }

    public function testCannotChangeToNonSliceIfUpperBoundIsMissing(): void
    {
        $indexes = new ArrayIndexes(new NumericConstant('1'), null, true);
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Upper bound is required');
        $indexes->isSlice = false;
    }

    public function testCannotChangeToNonSliceIfLowerBoundIsPresent(): void
    {
        $indexes = new ArrayIndexes(new NumericConstant('1'), new NumericConstant('2'), true);
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Lower bound should be omitted');
        $indexes->isSlice = false;
    }
}
