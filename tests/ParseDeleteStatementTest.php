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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_builder\Delete,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\CommonTableExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\Star,
    sad_spirit\pg_builder\nodes\WithClause,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\range\RelationReference,
    sad_spirit\pg_builder\nodes\range\UpdateOrDeleteTarget;

/**
 * Tests parsing all possible parts of DELETE statement
 */
class ParseDeleteStatementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseAllClauses()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
with foo (id) as (
    select somefoo from basefoo
)
delete from bar
using foo
where foo.id = bar.foo_id
returning *
QRY
        );

        $built = new Delete(new UpdateOrDeleteTarget(new QualifiedName(array('bar'))));
        $built->using->merge(array(
            new RelationReference(new QualifiedName(array('foo')))
        ));
        $built->where->condition = new OperatorExpression(
            '=', new ColumnReference(array('foo', 'id')), new ColumnReference(array('bar', 'foo_id'))
        );
        $built->returning->merge(new TargetList(array(new Star())));

        $cte = new Select(new TargetList(array(
            new TargetElement(new ColumnReference(array('somefoo')))
        )));
        $cte->from->replace(array(
            new RelationReference(new QualifiedName(array('basefoo')))
        ));

        $built->with = new WithClause(array(new CommonTableExpression(
            $cte, new Identifier('foo'), new IdentifierList(array(new Identifier('id')))
        )));

        $this->assertEquals($built, $parsed);
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\NotImplementedException
     * @expectedExceptionMessage WHERE CURRENT OF clause is not supported
     */
    public function testDisallowWhereCurrentOf()
    {
        $this->parser->parseStatement("delete from foo where current of blah");
    }
}