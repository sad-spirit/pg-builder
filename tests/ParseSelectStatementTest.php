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

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    Select,
    SetOpSelect,
};
use sad_spirit\pg_builder\enums\{
    LockingStrength,
    SetOperator,
    WindowFrameDirection,
    WindowFrameMode
};
use sad_spirit\pg_builder\exceptions\{
    SyntaxException,
    NotImplementedException
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    TypeName,
    ColumnReference,
    Identifier,
    LockingElement,
    QualifiedName,
    Star,
    TargetElement,
    WindowDefinition,
    WindowFrameBound,
    WindowFrameClause
};
use sad_spirit\pg_builder\nodes\expressions\{
    Constant,
    NumericConstant,
    StringConstant,
    TypecastExpression,
    FunctionExpression,
    OperatorExpression
};
use sad_spirit\pg_builder\nodes\lists\{
    FunctionArgumentList,
    TargetList,
    ExpressionList,
    LockList,
    WindowList
};
use sad_spirit\pg_builder\nodes\range\RelationReference;

/**
 * Tests parsing all possible parts of SELECT statement
 */
class ParseSelectStatementTest extends TestCase
{
    protected Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseAllSimpleSelectClauses(): void
    {
        $parsed = $this->parser->parseStatement(
            <<<QRY
select distinct on (foo) foo, bar, baz() over (win95) as blah, quux alias, xyzzy as as, foobar any
from one, two
where one.id = two.id
group by distinct bar
having count(quux) > 1
window win95 as ()
QRY
        );

        $built = new Select(
            new TargetList([
                new TargetElement(new ColumnReference('foo')),
                new TargetElement(new ColumnReference('bar')),
                new TargetElement(
                    new FunctionExpression(
                        new QualifiedName('baz'),
                        null,
                        false,
                        false,
                        null,
                        false,
                        null,
                        new WindowDefinition(new Identifier('win95'))
                    ),
                    new Identifier('blah')
                ),
                new TargetElement(new ColumnReference('quux'), new Identifier('alias')),
                new TargetElement(new ColumnReference('xyzzy'), new Identifier('as')),
                new TargetElement(new ColumnReference('foobar'), new Identifier('any'))
            ]),
            new ExpressionList([new ColumnReference('foo')])
        );

        $built->from->replace([
            new RelationReference(new QualifiedName('one')),
            new RelationReference(new QualifiedName('two'))
        ]);
        $built->where->condition = new OperatorExpression(
            '=',
            new ColumnReference('one', 'id'),
            new ColumnReference('two', 'id')
        );
        $built->group->replace([
            new ColumnReference('bar')
        ]);
        $built->group->distinct = true;
        $built->having->condition = new OperatorExpression(
            '>',
            new FunctionExpression(
                new QualifiedName('count'),
                new FunctionArgumentList([new ColumnReference('quux')])
            ),
            new NumericConstant('1')
        );
        $win95 = new WindowDefinition();
        $win95->setName(new Identifier('win95'));
        $built->window[] = $win95;

        $this->assertEquals($built, $parsed);
    }

    public function testSetOperationsPrecedence(): void
    {
        $list1 = $this->parser->parseStatement(
            <<<QRY
    select 'foo' union select 'bar' intersect select 'baz'
QRY
        );
        $list2 = $this->parser->parseStatement(
            <<<QRY
    (select 'foo' union select 'bar') intersect select 'baz'
QRY
        );

        $foo = new Select(new TargetList([new TargetElement(new StringConstant('foo'))]));

        $bar = new Select(new TargetList([new TargetElement(new StringConstant('bar'))]));

        $baz = new Select(new TargetList([new TargetElement(new StringConstant('baz'))]));

        $this->assertEquals(
            new SetOpSelect($foo, new SetOpSelect($bar, $baz, SetOperator::INTERSECT), SetOperator::UNION),
            $list1
        );
        $this->assertEquals(
            new SetOpSelect(
                new SetOpSelect(clone $foo, clone $bar, SetOperator::UNION),
                clone $baz,
                SetOperator::INTERSECT
            ),
            $list2
        );
    }

    #[DataProvider('getMultipleClausesQueries')]
    public function testPreventMultipleClauses(string $query): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Multiple');
        $this->parser->parseStatement($query);
    }

    public static function getMultipleClausesQueries(): array
    {
        return [
            ['(select * from foo order by 1) order by 2'],
            ['(select * from foo limit 1) limit 2'],
            ['(select * from foo offset 1) offset 2'],
            ['with a as (select * from foo) (with b as (select * from bar) select * from a natural join b)']
        ];
    }

    #[DataProvider('getLimitOffsetClauses')]
    public function testLimitOffsetClauses(
        string $stmt,
        int|float|ScalarExpression $limit,
        ?int $offset = null,
        bool $withTies = false
    ): void {
        $parsed = $this->parser->parseStatement($stmt);

        $built = new Select(new TargetList([new Star()]));
        $built->limit = \is_scalar($limit) ? Constant::createFromPHPValue($limit) : $limit;
        if ($offset) {
            $built->offset = Constant::createFromPHPValue($offset);
        }
        if ($withTies) {
            $built->limitWithTies = $withTies;
        }

        $this->assertEquals($built, $parsed);
    }

    public static function getLimitOffsetClauses(): array
    {
        return [
            ['select * limit 2 offset 3', 2, 3],
            [
             'select * offset 3 limit 1 + 1',
             new OperatorExpression('+', new NumericConstant('1'), new NumericConstant('1')),
             3
            ],
            ['select * offset 2 rows fetch first row only', 1, 2],
            ['select * fetch first 5 rows only', 5, null],
            [
                'select * fetch next (4 + 1) rows only',
                new OperatorExpression('+', new NumericConstant('4'), new NumericConstant('1')), null
            ],
            // fetch should allow float constant, not just integer
            ['select * fetch next +6.66 rows only', 6.66, null],
            // fetch should allow negative number, Postgres rejects that a bit later
            ['select * fetch first -1 row only', -1, null],
            // fetch should allow c_expr (our ExpressionAtom)
            [
                'select * fetch next cast(5 as integer) rows only',
                new TypecastExpression(new NumericConstant('5'), new TypeName(new QualifiedName('pg_catalog', 'int4'))),
                null
            ],
            // WITH TIES clause added in Postgres 13
            ['select * offset 5 rows fetch next 5 rows with ties', 5, 5, true]
        ];
    }

    public function testLockingClauses(): void
    {
        /** @var Select $select */
        $select = $this->parser->parseStatement(
            <<<QRY
    select * from a.foo, b.bar, c.baz for share of a.foo, c.baz for no key update of b.bar skip locked
QRY
        );
        $this->assertEquals(
            new LockList([
                new LockingElement(
                    LockingStrength::SHARE,
                    [new QualifiedName('a', 'foo'), new QualifiedName('c', 'baz')]
                ),
                new LockingElement(
                    LockingStrength::NO_KEY_UPDATE,
                    [new QualifiedName('b', 'bar')],
                    false,
                    true
                )
            ]),
            clone $select->locking
        );
    }

    public function testAllowMultipleLockingClauses(): void
    {
        /** @var Select $select */
        $select = $this->parser->parseStatement(
            <<<QRY
    (select * from foo, bar for update of foo) for update of bar nowait
QRY
        );
        $this->assertEquals(
            new LockList([
                new LockingElement(LockingStrength::UPDATE, [new QualifiedName('foo')]),
                new LockingElement(LockingStrength::UPDATE, [new QualifiedName('bar')], true)
            ]),
            clone $select->locking
        );
    }

    public function testCannotLockValues(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('cannot be applied to VALUES');
        $this->parser->parseStatement('values (1), (2) for update of foo');
    }

    public function testParseWindowClause(): void
    {
        /** @var Select $select */
        $select = $this->parser->parseStatement(
            <<<QRY
    select * window foo as (), bar as (foo), baz as (partition by whatever),
                    quux as (range between unbounded preceding and current row)
QRY
        );
        $windows = [
            new WindowDefinition(),
            new WindowDefinition(new Identifier('foo')),
            new WindowDefinition(null, new ExpressionList([new ColumnReference('whatever')])),
            new WindowDefinition(
                null,
                null,
                null,
                new WindowFrameClause(
                    WindowFrameMode::RANGE,
                    new WindowFrameBound(WindowFrameDirection::PRECEDING),
                    new WindowFrameBound(WindowFrameDirection::CURRENT_ROW)
                )
            )
        ];
        $windows[0]->setName(new Identifier('foo'));
        $windows[1]->setName(new Identifier('bar'));
        $windows[2]->setName(new Identifier('baz'));
        $windows[3]->setName(new Identifier('quux'));

        $this->assertEquals(new WindowList($windows), clone $select->window);
    }

    public function testDisallowSelectInto(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('SELECT INTO clauses are not supported');
        $this->parser->parseStatement('select foo into bar from baz');
    }

    public function testSelectWithEmptyTargetList(): void
    {
        $parsed = $this->parser->parseStatement('select');
        $this->assertEquals(new Select(new TargetList([])), $parsed);
    }

    public function testSelectFromTableWithEmptyTargetList(): void
    {
        $parsed = $this->parser->parseStatement('select from bar');

        $built = new Select(new TargetList([]));
        $built->from->replace([new RelationReference(new QualifiedName('bar'))]);

        $this->assertEquals($built, $parsed);
    }

    public function testComplexSelectWithEmptyTargetList(): void
    {
        $parsed = $this->parser->parseStatement('select union (select) intersect select limit 1');

        $built = new SetOpSelect(
            new Select(new TargetList([])),
            new SetOpSelect(
                new Select(new TargetList([])),
                new Select(new TargetList([])),
                SetOperator::INTERSECT
            )
        );
        $built->limit = new NumericConstant('1');

        $this->assertEquals($built, $parsed);
    }

    public function testDisallowedSelectDistinctWithEmptyTargetList(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unexpected end of input');
        $this->parser->parseStatement('select distinct');
    }
}
