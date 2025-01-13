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
    Lexer,
    Merge,
    Parser,
    Select
};
use sad_spirit\pg_builder\enums\{
    ConstantName,
    InsertOverriding
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    CommonTableExpression,
    Identifier,
    QualifiedName,
    SetTargetElement,
    SingleSetClause,
    TargetElement
};
use sad_spirit\pg_builder\nodes\expressions\{
    IsDistinctFromExpression,
    KeywordConstant,
    MergeAction,
    NumericConstant,
    OperatorExpression,
    StringConstant
};
use sad_spirit\pg_builder\nodes\lists\{
    SetClauseList,
    SetTargetList,
    TargetList
};
use sad_spirit\pg_builder\nodes\merge\{
    MergeDelete,
    MergeInsert,
    MergeUpdate,
    MergeValues,
    MergeWhenMatched,
    MergeWhenNotMatched
};
use sad_spirit\pg_builder\nodes\range\{
    RelationReference,
    UpdateOrDeleteTarget
};

/**
 * Tests parsing all possible parts of MERGE statement
 */
class ParseMergeStatementTest extends TestCase
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
with "null" as (
    select null as "null", 1 as one
)
merge into foo as bar
using "null"
on bar.id is not distinct from "null"
when not matched and one = 2 then
    insert (baz) overriding system value values ('quux')
when not matched by target and one > 2 then
    insert (baz) values ('duh')
when matched and baz <> 'quux' then
    update set baz = 'xyzzy'
when matched then 
    delete
when not matched by source then
    update set baz = 'blah'
returning bar.*, merge_action()
QRY
        );

        $built = new Merge(
            new UpdateOrDeleteTarget(new QualifiedName('foo'), new Identifier('bar')),
            new RelationReference(new QualifiedName('null')),
            new IsDistinctFromExpression(new ColumnReference('bar', 'id'), new ColumnReference('null'), true)
        );

        $built->with[] = new CommonTableExpression(
            new Select(new TargetList([
                new TargetElement(new KeywordConstant(ConstantName::NULL), new Identifier('null')),
                new TargetElement(new NumericConstant('1'), new Identifier('one'))
            ])),
            new Identifier('null')
        );

        $built->when[] = new MergeWhenNotMatched(
            new OperatorExpression('=', new ColumnReference('one'), new NumericConstant('2')),
            new MergeInsert(
                new SetTargetList([new SetTargetElement('baz')]),
                new MergeValues([new StringConstant('quux')]),
                InsertOverriding::SYSTEM
            )
        );

        $built->when[] = new MergeWhenNotMatched(
            new OperatorExpression('>', new ColumnReference('one'), new NumericConstant('2')),
            new MergeInsert(
                new SetTargetList([new SetTargetElement('baz')]),
                new MergeValues([new StringConstant('duh')])
            )
        );

        $built->when[] = new MergeWhenMatched(
            new OperatorExpression('<>', new ColumnReference('baz'), new StringConstant('quux')),
            new MergeUpdate(new SetClauseList([
                new SingleSetClause(new SetTargetElement(new Identifier('baz')), new StringConstant('xyzzy'))
            ]))
        );

        $built->when[] = new MergeWhenMatched(null, new MergeDelete());

        $built->when[] = new MergeWhenMatched(
            null,
            new MergeUpdate(new SetClauseList([
                new SingleSetClause(new SetTargetElement(new Identifier('baz')), new StringConstant('blah'))
            ])),
            false
        );

        $built->returning[] = new TargetElement(new ColumnReference('bar', '*'));
        $built->returning[] = new TargetElement(new MergeAction());

        $this::assertEquals($built, $parsed);
    }
}
