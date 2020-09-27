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
    sad_spirit\pg_builder\nodes\SingleSetClause,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\ArrayIndexes,
    sad_spirit\pg_builder\nodes\OnConflictClause,
    sad_spirit\pg_builder\nodes\IndexElement,
    sad_spirit\pg_builder\nodes\IndexParameters,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\nodes\lists\SetClauseList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\range\InsertTarget,
    sad_spirit\pg_builder\nodes\range\RelationReference;

/**
 * Tests parsing all possible parts of INSERT statement
 */
class ParseInsertStatementTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testInsertDefaultValues()
    {
        $parsed = $this->parser->parseStatement("insert into foo as bar default values");

        $built = new Insert(new InsertTarget(new QualifiedName(['foo']), new Identifier('bar')));

        $this->assertEquals($built, $parsed);
    }

    public function testParseAllClauses()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
with foo (id) as materialized (
    select somefoo from basefoo
),
bar (blah) as (
    select somebar from basebar
)
insert into baz as bzzz (one, two[1])
overriding system value
select id, blah
from foo, bar
where id < blah
on conflict do nothing
returning *
QRY
        );

        $built = new Insert(new InsertTarget(new QualifiedName(['baz']), new Identifier('bzzz')));
        $built->cols->replace([
            new SetTargetElement(new Identifier('one')),
            new SetTargetElement(new Identifier('two'), [new ArrayIndexes(new Constant(1))])
        ]);
        $built->overriding = 'system';
        $built->returning->replace([new Star()]);
        $built->onConflict = new OnConflictClause('nothing');

        $foo = new Select(new TargetList([
            new TargetElement(new ColumnReference(['somefoo']))
        ]));
        $foo->from->replace([
            new RelationReference(new QualifiedName(['basefoo']))
        ]);

        $bar = new Select(new TargetList([
            new TargetElement(new ColumnReference(['somebar']))
        ]));
        $bar->from->replace([
            new RelationReference(new QualifiedName(['basebar']))
        ]);

        $built->with = new WithClause([
            new CommonTableExpression($foo, new Identifier('foo'), new IdentifierList([new Identifier('id')]), true),
            new CommonTableExpression($bar, new Identifier('bar'), new IdentifierList([new Identifier('blah')]))
        ]);

        $built->values = new Select(new TargetList([
            new TargetElement(new ColumnReference(['id'])),
            new TargetElement(new ColumnReference(['blah']))
        ]));
        $built->values->from->replace([
            new RelationReference(new QualifiedName(['foo'])),
            new RelationReference(new QualifiedName(['bar']))
        ]);
        $built->values->where->condition = new OperatorExpression(
            '<', new ColumnReference(['id']), new ColumnReference(['blah'])
        );

        $this->assertEquals($built, $parsed);
    }

    /**
     * @dataProvider onConflictClauseProvider
     * @param string           $sql
     * @param OnConflictClause $expected
     */
    public function testParseOnConflictClause($sql, OnConflictClause $expected)
    {
        $this->assertEquals($expected, $this->parser->parseOnConflict($sql));
    }

    public function onConflictClauseProvider()
    {
        // directly from Postgres docs on the clause
        return [
            [
                '(did) DO UPDATE SET dname = EXCLUDED.dname',
                new OnConflictClause(
                    'update',
                    new IndexParameters([
                        new IndexElement(new Identifier('did'))
                    ]),
                    new SetClauseList([
                        new SingleSetClause(
                            new SetTargetElement(new Identifier('dname')),
                            new ColumnReference(['excluded', 'dname'])
                        )
                    ])
                )
            ],
            [
                '(did) DO UPDATE
                 SET dname = EXCLUDED.dname || \' (formerly \' || d.dname || \')\'
                 WHERE d.zipcode <> \'21201\'',
                new OnConflictClause(
                    'update',
                    new IndexParameters([
                        new IndexElement(new Identifier('did'))
                    ]),
                    new SetClauseList([
                        new SingleSetClause(
                            new SetTargetElement(new Identifier('dname')),
                            new OperatorExpression(
                                '||',
                                new OperatorExpression(
                                    '||',
                                    new OperatorExpression(
                                        '||',
                                        new ColumnReference(['excluded', 'dname']),
                                        new Constant(" (formerly ")
                                    ),
                                    new ColumnReference(['d', 'dname'])
                                ),
                                new Constant(")")
                            )
                        )
                    ]),
                    new OperatorExpression(
                        '<>',
                        new ColumnReference(['d', 'zipcode']),
                        new Constant("21201")
                    )
                )
            ],
            [
                'ON CONSTRAINT distributors_pkey DO NOTHING',
                new OnConflictClause(
                    'nothing',
                    new Identifier('distributors_pkey')
                )
            ]
        ];
    }
}