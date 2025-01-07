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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 *
 * @noinspection SqlNoDataSourceInspection, SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    Insert,
    Select,
    enums\InsertOverriding,
    enums\OnConflictAction
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    CommonTableExpression,
    expressions\NumericConstant,
    expressions\OperatorExpression,
    expressions\StringConstant,
    Star,
    WithClause,
    QualifiedName,
    TargetElement,
    SetTargetElement,
    SingleSetClause,
    Identifier,
    ArrayIndexes,
    OnConflictClause,
    IndexElement,
    IndexParameters,
    lists\IdentifierList,
    lists\SetClauseList,
    lists\TargetList,
    range\InsertTarget,
    range\RelationReference
};

/**
 * Tests parsing all possible parts of INSERT statement
 */
class ParseInsertStatementTest extends TestCase
{
    protected Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testInsertDefaultValues(): void
    {
        $parsed = $this->parser->parseStatement("insert into foo as bar default values");

        $built = new Insert(new InsertTarget(new QualifiedName('foo'), new Identifier('bar')));

        $this->assertEquals($built, $parsed);
    }

    public function testParseAllClauses(): void
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

        $built = new Insert(new InsertTarget(new QualifiedName('baz'), new Identifier('bzzz')));
        $built->cols->replace([
            new SetTargetElement(new Identifier('one')),
            new SetTargetElement(
                new Identifier('two'),
                [new ArrayIndexes(null, new NumericConstant('1'))]
            )
        ]);
        $built->overriding = InsertOverriding::SYSTEM;
        $built->returning->replace([new Star()]);
        $built->onConflict = new OnConflictClause(OnConflictAction::NOTHING);

        $foo = new Select(new TargetList([
            new TargetElement(new ColumnReference('somefoo'))
        ]));
        $foo->from->replace([
            new RelationReference(new QualifiedName('basefoo'))
        ]);

        $bar = new Select(new TargetList([
            new TargetElement(new ColumnReference('somebar'))
        ]));
        $bar->from->replace([
            new RelationReference(new QualifiedName('basebar'))
        ]);

        $built->with = new WithClause([
            new CommonTableExpression($foo, new Identifier('foo'), new IdentifierList([new Identifier('id')]), true),
            new CommonTableExpression($bar, new Identifier('bar'), new IdentifierList([new Identifier('blah')]))
        ]);

        $built->values = new Select(new TargetList([
            new TargetElement(new ColumnReference('id')),
            new TargetElement(new ColumnReference('blah'))
        ]));
        $built->values->from->replace([
            new RelationReference(new QualifiedName('foo')),
            new RelationReference(new QualifiedName('bar'))
        ]);
        $built->values->where->condition = new OperatorExpression(
            '<',
            new ColumnReference('id'),
            new ColumnReference('blah')
        );

        $this->assertEquals($built, $parsed);
    }

    #[DataProvider('onConflictClauseProvider')]
    public function testParseOnConflictClause(string $sql, OnConflictClause $expected): void
    {
        $this->assertEquals($expected, $this->parser->parseOnConflict($sql));
    }

    public static function onConflictClauseProvider(): array
    {
        // directly from Postgres docs on the clause
        return [
            [
                '(did) DO UPDATE SET dname = EXCLUDED.dname',
                new OnConflictClause(
                    OnConflictAction::UPDATE,
                    new IndexParameters([
                        new IndexElement(new Identifier('did'))
                    ]),
                    new SetClauseList([
                        new SingleSetClause(
                            new SetTargetElement(new Identifier('dname')),
                            new ColumnReference('excluded', 'dname')
                        )
                    ])
                )
            ],
            [
                "(did) DO UPDATE
                 SET dname = EXCLUDED.dname || ' (formerly ' || d.dname || ')'
                 WHERE d.zipcode <> '21201'",
                new OnConflictClause(
                    OnConflictAction::UPDATE,
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
                                        new ColumnReference('excluded', 'dname'),
                                        new StringConstant(" (formerly ")
                                    ),
                                    new ColumnReference('d', 'dname')
                                ),
                                new StringConstant(")")
                            )
                        )
                    ]),
                    new OperatorExpression(
                        '<>',
                        new ColumnReference('d', 'zipcode'),
                        new StringConstant("21201")
                    )
                )
            ],
            [
                'ON CONSTRAINT distributors_pkey DO NOTHING',
                new OnConflictClause(
                    OnConflictAction::NOTHING,
                    new Identifier('distributors_pkey')
                )
            ]
        ];
    }
}
