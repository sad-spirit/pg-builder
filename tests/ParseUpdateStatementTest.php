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

use sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_builder\Update,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\CommonTableExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\expressions\RowExpression,
    sad_spirit\pg_builder\nodes\expressions\SubselectExpression,
    sad_spirit\pg_builder\nodes\Star,
    sad_spirit\pg_builder\nodes\WithClause,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\SetTargetElement,
    sad_spirit\pg_builder\nodes\MultipleSetClause,
    sad_spirit\pg_builder\nodes\SingleSetClause,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\ArrayIndexes,
    sad_spirit\pg_builder\nodes\SetToDefault,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\nodes\lists\SetClauseList,
    sad_spirit\pg_builder\nodes\lists\SetTargetList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\range\RelationReference,
    sad_spirit\pg_builder\nodes\range\UpdateOrDeleteTarget;

/**
 * Tests parsing all possible parts of UPDATE statement
 */
class ParseUpdateStatementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseSetClause()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
    update foo bar set blah.one = 'blah', blahblah = default, (baz[1], quux) = ('quux', default),
           (a, b, c) = (select aa, bb, cc from somewhere), (d, e) = row(v.*)
QRY
        );
        $update = new Update(
            new UpdateOrDeleteTarget(new QualifiedName(array('foo')), new Identifier('bar')),
            new SetClauseList(array(
                new SingleSetClause(
                    new SetTargetElement(new Identifier('blah'), array(new Identifier('one'))),
                    new Constant('blah')
                ),
                new SingleSetClause(
                    new SetTargetElement(new Identifier('blahblah')),
                    new SetToDefault()
                ),
                new MultipleSetClause(
                    new SetTargetList(array(
                        new SetTargetElement(new Identifier('baz'), array(new ArrayIndexes(new Constant(1)))),
                        new SetTargetElement(new Identifier('quux'))
                    )),
                    new RowExpression(array(
                        new Constant('quux'),
                        new SetToDefault()
                    ))
                ),
                new MultipleSetClause(
                    new SetTargetList(array(
                        new SetTargetElement(new Identifier('a')),
                        new SetTargetElement(new Identifier('b')),
                        new SetTargetElement(new Identifier('c')),
                    )),
                    new SubselectExpression($select = new Select(new TargetList(array(
                            new TargetElement(new ColumnReference(array('aa'))),
                            new TargetElement(new ColumnReference(array('bb'))),
                            new TargetElement(new ColumnReference(array('cc')))
                    ))))
                ),
                new MultipleSetClause(
                    new SetTargetList(array(
                        new SetTargetElement(new Identifier('d')),
                        new SetTargetElement(new Identifier('e')),
                    )),
                    new RowExpression(array(
                        new ColumnReference(array('v', '*'))
                    ))
                )
            ))
        );
        $select->from[] = new RelationReference(new QualifiedName(array('somewhere')));

        $this->assertEquals($update, $parsed);
    }

    public function testTreatSetAsKeyword()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
    update foo set set = 'set'
QRY
        );
        $update = new Update(
            new UpdateOrDeleteTarget(new QualifiedName(array('foo'))),
            new SetClauseList(array(
                new SingleSetClause(
                    new SetTargetElement(new Identifier('set')),
                    new Constant('set')
                )
            ))
        );

        $this->assertEquals($update, $parsed);
    }

    public function testParseAllClauses()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
with foo as not materialized (
    select somefoo from basefoo
)
update bar
set blah = foo.blah
from foo
where foo.id = bar.foo_id
returning *
QRY
        );

        $built = new Update(
            new UpdateOrDeleteTarget(new QualifiedName(array('bar'))),
            new SetClauseList(array(
                new SingleSetClause(
                    new SetTargetElement(new Identifier('blah')),
                    new ColumnReference(array('foo', 'blah'))
                )
            ))
        );
        $built->from->replace(array(
            new RelationReference(new QualifiedName(array('foo')))
        ));
        $built->where->condition = new OperatorExpression(
            '=', new ColumnReference(array('foo', 'id')), new ColumnReference(array('bar', 'foo_id'))
        );
        $built->returning->replace(array(new Star()));

        $cte = new Select(new TargetList(array(
            new TargetElement(new ColumnReference(array('somefoo')))
        )));
        $cte->from->replace(array(
            new RelationReference(new QualifiedName(array('basefoo')))
        ));

        $built->with = new WithClause(array(
            new CommonTableExpression($cte, new Identifier('foo'), new IdentifierList(), false)
        ));

        $this->assertEquals($built, $parsed);
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\NotImplementedException
     * @expectedExceptionMessage WHERE CURRENT OF clause is not supported
     */
    public function testDisallowWhereCurrentOf()
    {
        $this->parser->parseStatement("update foo set bar = 'bar' where current of blah");
    }
}