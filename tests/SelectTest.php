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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 *
 * @noinspection SqlNoDataSourceInspection, SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Lexer,
    Parser,
    Select,
    SetOpSelect,
    SqlBuilderWalker
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Star,
    expressions\InExpression,
    expressions\SubselectExpression,
    lists\ExpressionList,
    lists\TargetList
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Tests helper methods of Select node
 */
class SelectTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;
    /**
     * @var SqlBuilderWalker
     */
    protected $builder;

    protected function setUp(): void
    {
        $this->parser  = new Parser(new Lexer());
        $this->builder = new SqlBuilderWalker([
            'indent'    => '',
            'linebreak' => '',
            'wrap'      => null
        ]);
    }

    public function testCannotCombineWithItself(): void
    {
        $select = new Select(new TargetList([new Star()]));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Cannot combine a SELECT statement with itself');
        new SetOpSelect($select, $select);
    }

    public function testCannotCombineWithItselfUsingHelperMethod(): void
    {
        $select = new Select(new TargetList([new Star()]));

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Cannot combine a SELECT statement with itself');
        $select->union($select);
    }

    public function testSimpleUnion(): void
    {
        $select = $this->parser->parseSelectStatement('select * from foo');
        $select->setParser($this->parser);

        $setOp = $select->union('select * from bar', false);
        $this->assertSame($this->parser, $setOp->getParser());
        $this->assertEquals(
            'select * from foo union all select * from bar',
            $setOp->dispatch($this->builder)
        );
    }

    public function testNestedSetOp(): void
    {
        /** @var SetOpSelect $setOp */
        $setOp = $this->parser->parseSelectStatement('select * from foo intersect select * from bar');
        $setOp->right->except($this->parser->parseSelectStatement('select * from baz'));
        $this->assertEquals(
            'select * from foo intersect (select * from bar except select * from baz)',
            $setOp->dispatch($this->builder)
        );
    }

    /**
     * @psalm-suppress UndefinedMagicPropertyFetch
     */
    public function testRangeSubselectSetOp(): void
    {
        /** @var Select $select */
        $select = $this->parser->parseSelectStatement('select foo.* from (select * from foosource) as foo');
        $select->setParser($this->parser);

        // @phpstan-ignore-next-line
        $select->from[0]->query->intersect('select * from barsource');
        $this->assertEquals(
            'select foo.* from (select * from foosource intersect select * from barsource) as foo',
            $select->dispatch($this->builder)
        );
    }

    /**
     * @psalm-suppress UndefinedInterfaceMethod, PossiblyUndefinedMethod, PossiblyNullPropertyFetch
     */
    public function testScalarSubselectSetOp(): void
    {
        /** @var Select $select */
        $select = $this->parser->parseSelectStatement(
            'select * from foo where foo_id in (select id from bar) or foo_name > any(select baz_name from baz)'
        );
        $select->setParser($this->parser);
        /** @var InExpression $in */
        $in = $select->where->condition[0];
        $in->right->union('select id from quux');
        /** @var SubselectExpression $any */
        $any = $select->where->condition[1]->right;
        $any->query->except('select xyzzy_name from xyzzy');

        $this->assertEquals(
            'select * from foo where foo_id in (select id from bar union select id from quux)'
            . ' or foo_name > any(select baz_name from baz except select xyzzy_name from xyzzy)',
            $select->dispatch($this->builder)
        );
    }

    /**
     * @psalm-suppress InvalidMethodCall, RedundantCondition
     */
    public function testSetDistinct(): void
    {
        $select = new Select(new TargetList([new Star()]), true);
        $this::assertTrue($select->distinct);

        $select->setDistinct($list = new ExpressionList([new ColumnReference('foo')]));
        $this::assertEquals(['foo'], $select->distinct->dispatch($this->builder));
        $this::assertSame($select, $list->getParentNode());

        $select->setDistinct(false);
        $this::assertFalse($select->distinct);
        $this::assertNull($list->getParentNode());

        $select->distinct = true;
        $this::assertTrue($select->distinct);
    }
}
