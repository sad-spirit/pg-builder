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
 *
 * @noinspection SqlNoDataSourceInspection, SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    Select,
    Update,
    exceptions\NotImplementedException,
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    CommonTableExpression,
    Star,
    WithClause,
    QualifiedName,
    SetTargetElement,
    MultipleSetClause,
    SingleSetClause,
    TargetElement,
    Identifier,
    ArrayIndexes,
    SetToDefault
};
use sad_spirit\pg_builder\nodes\expressions\{
    NumericConstant,
    OperatorExpression,
    RowExpression,
    StringConstant,
    SubselectExpression
};
use sad_spirit\pg_builder\nodes\lists\{
    IdentifierList,
    SetClauseList,
    SetTargetList,
    TargetList
};
use sad_spirit\pg_builder\nodes\range\{
    RelationReference,
    UpdateOrDeleteTarget
};

/**
 * Tests parsing all possible parts of UPDATE statement
 */
class ParseUpdateStatementTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
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
            new UpdateOrDeleteTarget(new QualifiedName('foo'), new Identifier('bar')),
            new SetClauseList([
                new SingleSetClause(
                    new SetTargetElement(new Identifier('blah'), [new Identifier('one')]),
                    new StringConstant('blah')
                ),
                new SingleSetClause(
                    new SetTargetElement(new Identifier('blahblah')),
                    new SetToDefault()
                ),
                new MultipleSetClause(
                    new SetTargetList([
                        new SetTargetElement(new Identifier('baz'), [new ArrayIndexes(new NumericConstant('1'))]),
                        new SetTargetElement(new Identifier('quux'))
                    ]),
                    new RowExpression([
                        new StringConstant('quux'),
                        new SetToDefault()
                    ])
                ),
                new MultipleSetClause(
                    new SetTargetList([
                        new SetTargetElement(new Identifier('a')),
                        new SetTargetElement(new Identifier('b')),
                        new SetTargetElement(new Identifier('c')),
                    ]),
                    new SubselectExpression($select = new Select(new TargetList([
                            new TargetElement(new ColumnReference('aa')),
                            new TargetElement(new ColumnReference('bb')),
                            new TargetElement(new ColumnReference('cc'))
                    ])))
                ),
                new MultipleSetClause(
                    new SetTargetList([
                        new SetTargetElement(new Identifier('d')),
                        new SetTargetElement(new Identifier('e')),
                    ]),
                    new RowExpression([
                        new ColumnReference('v', '*')
                    ])
                )
            ])
        );
        $select->from[] = new RelationReference(new QualifiedName('somewhere'));

        $this->assertEquals($update, $parsed);
    }

    public function testTreatSetAsKeyword()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
    update foo set set = 'set'
QRY
        );
        $update = new Update(
            new UpdateOrDeleteTarget(new QualifiedName('foo')),
            new SetClauseList([
                new SingleSetClause(
                    new SetTargetElement(new Identifier('set')),
                    new StringConstant('set')
                )
            ])
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
            new UpdateOrDeleteTarget(new QualifiedName('bar')),
            new SetClauseList([
                new SingleSetClause(
                    new SetTargetElement(new Identifier('blah')),
                    new ColumnReference('foo', 'blah')
                )
            ])
        );
        $built->from->replace([
            new RelationReference(new QualifiedName('foo'))
        ]);
        $built->where->condition = new OperatorExpression(
            '=',
            new ColumnReference('foo', 'id'),
            new ColumnReference('bar', 'foo_id')
        );
        $built->returning->replace([new Star()]);

        $cte = new Select(new TargetList([
            new TargetElement(new ColumnReference('somefoo'))
        ]));
        $cte->from->replace([
            new RelationReference(new QualifiedName('basefoo'))
        ]);

        $built->with = new WithClause([
            new CommonTableExpression($cte, new Identifier('foo'), new IdentifierList(), false)
        ]);

        $this->assertEquals($built, $parsed);
    }

    public function testDisallowWhereCurrentOf()
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('WHERE CURRENT OF clause is not supported');
        $this->parser->parseStatement("update foo set bar = 'bar' where current of blah");
    }
}
