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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 *
 * @noinspection SqlNoDataSourceInspection, SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Insert,
    Lexer,
    Merge,
    Parser,
    Select
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
with "null" as (
    select null as "null", 1 as one
)
merge into foo as bar
using "null"
on bar.id is not distinct from "null"
when not matched and one = 2 then
    insert (baz) overriding system value values ('quux')
when matched and baz <> 'quux' then
    update set baz = 'xyzzy'
when matched then 
    delete 
QRY
        );

        $built = new Merge(
            new UpdateOrDeleteTarget(new QualifiedName('foo'), new Identifier('bar')),
            new RelationReference(new QualifiedName('null')),
            new IsDistinctFromExpression(new ColumnReference('bar', 'id'), new ColumnReference('null'), true)
        );

        $built->with[] = new CommonTableExpression(
            new Select(new TargetList([
                new TargetElement(new KeywordConstant(KeywordConstant::NULL), new Identifier('null')),
                new TargetElement(new NumericConstant('1'), new Identifier('one'))
            ])),
            new Identifier('null')
        );

        $built->when[] = new MergeWhenNotMatched(
            new OperatorExpression('=', new ColumnReference('one'), new NumericConstant('2')),
            new MergeInsert(
                new SetTargetList([new SetTargetElement('baz')]),
                new MergeValues([new StringConstant('quux')]),
                Insert::OVERRIDING_SYSTEM
            )
        );

        $built->when[] = new MergeWhenMatched(
            new OperatorExpression('<>', new ColumnReference('baz'), new StringConstant('quux')),
            new MergeUpdate(new SetClauseList([
                new SingleSetClause(new SetTargetElement(new Identifier('baz')), new StringConstant('xyzzy'))
            ]))
        );

        $built->when[] = new MergeWhenMatched(null, new MergeDelete());

        $this::assertEquals($built, $parsed);
    }
}
