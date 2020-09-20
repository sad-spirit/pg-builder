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

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\nodes\expressions\TypecastExpression;
use sad_spirit\pg_builder\nodes\TypeName;
use sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\LockingElement,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\Star,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\WindowDefinition,
    sad_spirit\pg_builder\nodes\WindowFrameBound,
    sad_spirit\pg_builder\nodes\WindowFrameClause,
    sad_spirit\pg_builder\nodes\expressions\FunctionExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\lists\FunctionArgumentList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\lists\LockList,
    sad_spirit\pg_builder\nodes\lists\WindowList,
    sad_spirit\pg_builder\nodes\range\RelationReference,
    sad_spirit\pg_builder\SetOpSelect;

/**
 * Tests parsing all possible parts of SELECT statement
 */
class ParseSelectStatementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseAllSimpleSelectClauses()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
select distinct on (foo) foo, bar, baz() over (win95) as blah, quux alias
from one, two
where one.id = two.id
group by bar
having count(quux) > 1
window win95 as ()
QRY
        );

        $built = new Select(
            new TargetList(array(
                new TargetElement(new ColumnReference(array('foo'))),
                new TargetElement(new ColumnReference(array('bar'))),
                new TargetElement(
                    new FunctionExpression(
                        new QualifiedName(array('baz')), null, false, false, null,
                        null, null, new WindowDefinition(new Identifier('win95'))
                    ),
                    new Identifier('blah')
                ),
                new TargetElement(new ColumnReference(array('quux')), new Identifier('alias'))
            )),
            new ExpressionList(array(new ColumnReference(array('foo'))))
        );

        $built->from->replace(array(
            new RelationReference(new QualifiedName(array('one'))),
            new RelationReference(new QualifiedName(array('two')))
        ));
        $built->where->condition = new OperatorExpression(
            '=', new ColumnReference(array('one', 'id')), new ColumnReference(array('two', 'id'))
        );
        $built->group->replace(array(
            new ColumnReference(array('bar'))
        ));
        $built->having->condition = new OperatorExpression(
            '>', new FunctionExpression(
                new QualifiedName(array('count')),
                new FunctionArgumentList(array(new ColumnReference(array('quux'))))
            ), new Constant(1)
        );
        $win95 = new WindowDefinition();
        $win95->setName(new Identifier('win95'));
        $built->window[] = $win95;

        $this->assertEquals($built, $parsed);
    }

    public function testSetOperationsPrecedence()
    {
        $list1 = $this->parser->parseStatement(<<<QRY
    select 'foo' union select 'bar' intersect select 'baz'
QRY
        );
        $list2 = $this->parser->parseStatement(<<<QRY
    (select 'foo' union select 'bar') intersect select 'baz'
QRY
        );

        $foo = new Select(new TargetList(array(new TargetElement(new Constant('foo')))));

        $bar = new Select(new TargetList(array(new TargetElement(new Constant('bar')))));

        $baz = new Select(new TargetList(array(new TargetElement(new Constant('baz')))));

        $this->assertEquals(
            new SetOpSelect($foo, new SetOpSelect($bar, $baz, 'intersect'), 'union'),
            $list1
        );
        $this->assertEquals(
            new SetOpSelect(new SetOpSelect(clone $foo, clone $bar, 'union'), clone $baz, 'intersect'),
            $list2
        );
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Multiple
     * @dataProvider getMultipleClausesQueries
     */
    public function testPreventMultipleClauses($query)
    {
        $this->parser->parseStatement($query);
    }

    public function getMultipleClausesQueries()
    {
        return array(
            array('(select * from foo order by 1) order by 2'),
            array('(select * from foo limit 1) limit 2'),
            array('(select * from foo offset 1) offset 2'),
            array('with a as (select * from foo) (with b as (select * from bar) select * from a natural join b)')
        );
    }

    /**
     * @dataProvider getLimitOffsetClauses
     */
    public function testLimitOffsetClauses($stmt, $limit, $offset, $withTies = false)
    {
        $parsed = $this->parser->parseStatement($stmt);

        $built = new Select(new TargetList(array(new Star())));
        if (is_scalar($limit)) {
            $built->limit = new Constant($limit);
        } else {
            $built->limit = $limit;
        }
        if ($offset) {
            $built->offset = new Constant($offset);
        }
        if ($withTies) {
            $built->limitWithTies = $withTies;
        }

        $this->assertEquals($built, $parsed);
    }

    public function getLimitOffsetClauses()
    {
        return array(
            array('select * limit 2 offset 3', 2, 3),
            array('select * offset 3 limit 1 + 1', new OperatorExpression('+', new Constant(1), new Constant(1)), 3),
            array('select * offset 2 rows fetch first row only', 1, 2),
            array('select * fetch first 5 rows only', 5, null),
            array('select * fetch next (4 + 1) rows only', new OperatorExpression('+', new Constant(4), new Constant(1)), null),
            // fetch should allow float constant, not just integer
            array('select * fetch next +6.66 rows only', 6.66, null),
            // fetch should allow negative number, Postgres rejects that a bit later
            array('select * fetch first -1 row only', -1, null),
            // fetch should allow c_expr (our ExpressionAtom)
            array(
                'select * fetch next cast(5 as integer) rows only',
                new TypecastExpression(new Constant(5), new TypeName(new QualifiedName(array('pg_catalog', 'int4')))),
                null
            ),
            // WITH TIES clause added in Postgres 13
            array('select * offset 5 rows fetch next 5 rows with ties', 5, 5, true)
        );
    }

    public function testLockingClauses()
    {
        $select = $this->parser->parseStatement(<<<QRY
    select * from a.foo, b.bar, c.baz for share of a.foo, c.baz for no key update of b.bar skip locked
QRY
        );
        $this->assertEquals(
            new LockList(array(
                new LockingElement('share', array(new QualifiedName(array('a', 'foo')), new QualifiedName(array('c', 'baz')))),
                new LockingElement('no key update', array(new QualifiedName(array('b', 'bar'))), false, true)
            )),
            clone $select->locking
        );
    }

    public function testAllowMultipleLockingClauses()
    {
        $select = $this->parser->parseStatement(<<<QRY
    (select * from foo, bar for update of foo) for update of bar nowait
QRY
        );
        $this->assertEquals(
            new LockList(array(
                new LockingElement('update', array(new QualifiedName(array('foo')))),
                new LockingElement('update', array(new QualifiedName(array('bar'))), true)
            )),
            clone $select->locking
        );
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage cannot be applied to VALUES
     */
    public function testCannotLockValues()
    {
        $this->parser->parseStatement('values (1), (2) for update of foo');
    }

    public function testParseWindowClause()
    {
        $select = $this->parser->parseStatement(<<<QRY
    select * window foo as (), bar as (foo), baz as (partition by whatever),
                    quux as (range between unbounded preceding and current row)
QRY
        );
        $windows = array(
            new WindowDefinition(),
            new WindowDefinition(new Identifier('foo')),
            new WindowDefinition(null, new ExpressionList(array(new ColumnReference(array('whatever'))))),
            new WindowDefinition(
                null, null, null,
                new WindowFrameClause(
                    'range', new WindowFrameBound('preceding'), new WindowFrameBound('current row')
                )
            )
        );
        $windows[0]->setName(new Identifier('foo'));
        $windows[1]->setName(new Identifier('bar'));
        $windows[2]->setName(new Identifier('baz'));
        $windows[3]->setName(new Identifier('quux'));

        $this->assertEquals(new WindowList($windows), clone $select->window);
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\NotImplementedException
     * @expectedExceptionMessage SELECT INTO clauses are not supported
     */
    public function testDisallowSelectInto()
    {
        $this->parser->parseStatement('select foo into bar from baz');
    }
}