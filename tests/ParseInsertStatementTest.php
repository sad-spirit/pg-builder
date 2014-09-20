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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Insert,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\CommonTableExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\Star,
    sad_spirit\pg_builder\nodes\WithClause,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\SetTargetElement,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\ArrayIndexes,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\range\RelationReference;

/**
 * Tests parsing all possible parts of INSERT statement
 */
class ParseInsertStatementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testInsertDefaultValues()
    {
        $parsed = $this->parser->parseStatement("insert into foo default values");

        $built = new Insert(new QualifiedName(array('foo')));

        $this->assertEquals($built, $parsed);
    }

    public function testParseAllClauses()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
with foo (id) as (
    select somefoo from basefoo
),
bar (blah) as (
    select somebar from basebar
)
insert into baz (one, two[1])
select id, blah
from foo, bar
where id < blah
returning *
QRY
        );

        $built = new Insert(new QualifiedName(array('baz')));
        $built->cols->replace(array(
            new SetTargetElement(new Identifier('one')),
            new SetTargetElement(new Identifier('two'), array(new ArrayIndexes(new Constant(1))))
        ));
        $built->returning->replace(array(new Star()));

        $foo = new Select(new TargetList(array(
            new TargetElement(new ColumnReference(array('somefoo')))
        )));
        $foo->from->replace(array(
            new RelationReference(new QualifiedName(array('basefoo')))
        ));

        $bar = new Select(new TargetList(array(
            new TargetElement(new ColumnReference(array('somebar')))
        )));
        $bar->from->replace(array(
            new RelationReference(new QualifiedName(array('basebar')))
        ));

        $built->with = new WithClause(array(
            new CommonTableExpression($foo, new Identifier('foo'), new IdentifierList(array(new Identifier('id')))),
            new CommonTableExpression($bar, new Identifier('bar'), new IdentifierList(array(new Identifier('blah'))))
        ));

        $built->values = new Select(new TargetList(array(
            new TargetElement(new ColumnReference(array('id'))),
            new TargetElement(new ColumnReference(array('blah')))
        )));
        $built->values->from->replace(array(
            new RelationReference(new QualifiedName(array('foo'))),
            new RelationReference(new QualifiedName(array('bar')))
        ));
        $built->values->where->condition = new OperatorExpression(
            '<', new ColumnReference(array('id')), new ColumnReference(array('blah'))
        );

        $this->assertEquals($built, $parsed);
    }
}