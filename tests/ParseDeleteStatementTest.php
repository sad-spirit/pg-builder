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
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseAllClauses(): void
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
