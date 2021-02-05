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

namespace sad_spirit\pg_builder\tests\nodes\expressions;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    expressions\ArrayExpression,
    expressions\CaseExpression,
    expressions\RowExpression,
    expressions\StringConstant,
    expressions\WhenExpression,
    lists\ExpressionList
};

/**
 * Tests the specific behaviour of ArrayExpression nodes
 */
class ArrayExpressionTest extends TestCase
{
    public function testConvertsIterableToArrayExpression(): void
    {
        $array = new ArrayExpression();
        $array[0] = [new StringConstant('foo'), new ColumnReference('bar')];
        $array[1] = new ExpressionList([new StringConstant('baz'), new ColumnReference('quux')]);

        $this::assertInstanceOf(ArrayExpression::class, $array[0]);
        $this::assertInstanceOf(ArrayExpression::class, $array[1]);

        $this::assertEquals(new StringConstant('foo'), clone $array[0][0]);
        $this::assertEquals(new ColumnReference('quux'), clone $array[1][1]);
    }

    public function testLeavesScalarExpressionsAlone(): void
    {
        $array = new ArrayExpression();
        $row   = new RowExpression([new StringConstant('foo'), new ColumnReference('bar')]);
        $case  = new CaseExpression(
            [new WhenExpression(new ColumnReference('foo'), new StringConstant('bar'))],
            new StringConstant('baz')
        );

        $array[0] = $row;
        $array[1] = $case;

        $this::assertSame($row, $array[0]);
        $this::assertSame($case, $array[1]);
    }
}
