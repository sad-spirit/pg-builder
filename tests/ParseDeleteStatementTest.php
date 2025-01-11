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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    Select,
    Delete
};
use sad_spirit\pg_builder\exceptions\NotImplementedException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    CommonTableExpression,
    expressions\OperatorExpression,
    Star,
    WithClause,
    QualifiedName,
    TargetElement,
    Identifier,
    lists\IdentifierList,
    lists\TargetList,
    range\RelationReference,
    range\UpdateOrDeleteTarget
};

/**
 * Tests parsing all possible parts of DELETE statement
 */
class ParseDeleteStatementTest extends TestCase
{
    protected Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseAllClauses(): void
    {
        $parsed = $this->parser->parseStatement(
            <<<QRY
with foo (id) as (
    select somefoo from basefoo
)
delete from bar
using foo
where foo.id = bar.foo_id
returning *
QRY
        );

        $built = new Delete(new UpdateOrDeleteTarget(new QualifiedName('bar')));
        $built->using->merge([
            new RelationReference(new QualifiedName('foo'))
        ]);
        $built->where->condition = new OperatorExpression(
            '=',
            new ColumnReference('foo', 'id'),
            new ColumnReference('bar', 'foo_id')
        );
        $built->returning->merge(new TargetList([new Star()]));

        $cte = new Select(new TargetList([
            new TargetElement(new ColumnReference('somefoo'))
        ]));
        $cte->from->replace([
            new RelationReference(new QualifiedName('basefoo'))
        ]);

        $built->with = new WithClause([new CommonTableExpression(
            $cte,
            new Identifier('foo'),
            new IdentifierList([new Identifier('id')])
        )]);

        $this->assertEquals($built, $parsed);
    }

    /**
     * @noinspection SqlNoDataSourceInspection, SqlResolve
     */
    public function testDisallowWhereCurrentOf(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->expectExceptionMessage('WHERE CURRENT OF clause is not supported');
        $this->parser->parseStatement("delete from foo where current of blah");
    }
}
