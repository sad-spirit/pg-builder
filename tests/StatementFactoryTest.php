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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_builder\{
    Lexer,
    Parser,
    SqlBuilderWalker,
    StatementFactory
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    QualifiedName,
    SetTargetElement,
    SetToDefault,
    SingleSetClause,
    TargetElement
};
use sad_spirit\pg_builder\nodes\expressions\{
    NumericConstant,
    OperatorExpression,
    StringConstant
};
use sad_spirit\pg_builder\nodes\lists\{
    RowList,
    FromList,
    SetClauseList,
    TargetList
};
use sad_spirit\pg_builder\nodes\merge\MergeWhenMatched;
use sad_spirit\pg_builder\nodes\range\{
    InsertTarget,
    RelationReference,
    UpdateOrDeleteTarget
};
use Psr\Cache\CacheItemPoolInterface;

/**
 * Unit test for StatementFactory class
 */
class StatementFactoryTest extends TestCase
{
    public function testCreatesDefaultParser(): void
    {
        $factory = new StatementFactory();
        $this->assertEquals(
            new Parser(new Lexer()),
            $factory->getParser()
        );
    }

    public function testCreatesFactoryForConnection(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        /* @var $mockPool CacheItemPoolInterface|MockObject */
        $cache      = $this->createMock(CacheItemPoolInterface::class);
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING);
        $connection->execute("set standard_conforming_strings = off");
        $connection->execute("set client_encoding = 'windows-1251'");
        $connection->setMetadataCache($cache);

        $factory         = StatementFactory::forConnection($connection);
        $expectedParser  = new Parser(new Lexer(['standard_conforming_strings' => false]), $cache);
        $expectedBuilder = new SqlBuilderWalker(['escape_unicode' => true]);

        $this::assertEquals($expectedParser, $factory->getParser());
        $this::assertEquals($expectedBuilder, $factory->getBuilder());
    }

    public function testCreatesFactoryForPDO(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_BUILDER_PDO_DSN) {
            $this::markTestSkipped('PDO DSN is not configured');
        }

        $pdo = new \PDO(TESTS_SAD_SPIRIT_PG_BUILDER_PDO_DSN);
        $pdo->exec('set standard_conforming_strings = off');
        $pdo->exec("set client_encoding = 'windows-1251'");

        $factory         = StatementFactory::forPDO($pdo);
        $expectedParser  = new Parser(new Lexer(['standard_conforming_strings' => false]));
        $expectedBuilder = new SqlBuilderWalker(['escape_unicode' => true]);

        $this::assertEquals($expectedParser, $factory->getParser());
        $this::assertEquals($expectedBuilder, $factory->getBuilder());
    }

    /**
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     */
    public function testSetsParserOnCreatingFromString(): void
    {
        $factory = new StatementFactory();
        $select  = $factory->createFromString('select foo from bar');
        $this->assertSame($factory->getParser(), $select->getParser());
    }

    public function testCreateDeleteStatement(): void
    {
        $factory = new StatementFactory();

        $delete   = $factory->delete('only foo as bar');
        $relation = new UpdateOrDeleteTarget(
            new QualifiedName('foo'),
            new Identifier('bar'),
            false
        );
        $this->assertEquals($relation, clone $delete->relation);
        $this->assertSame($factory->getParser(), $delete->getParser());

        $delete2 = $factory->delete($relation);
        $this->assertSame($relation, $delete2->relation);
    }

    public function testCreateInsertStatement(): void
    {
        $factory = new StatementFactory();

        $insert   = $factory->insert('someschema.target as aliaz');
        $target   = new InsertTarget(
            new QualifiedName('someschema', 'target'),
            new Identifier('aliaz')
        );
        $this->assertEquals($target, clone $insert->relation);
        $this->assertSame($factory->getParser(), $insert->getParser());

        $insert2 = $factory->insert($target);
        $this->assertSame($target, $insert2->relation);
    }

    public function testCreateMergeStatement(): void
    {
        $factory = new StatementFactory();

        $merge = $factory->merge('foo as target', 'foo as source', 'source.id = target.id');
        $merge->when[] = 'when matched then do nothing';

        $relation  = new UpdateOrDeleteTarget(new QualifiedName('foo'), new Identifier('target'));
        $joined    = new RelationReference(new QualifiedName('foo'));
        $joined->setAlias(new Identifier('source'));
        $condition = new OperatorExpression(
            '=',
            new ColumnReference('source', 'id'),
            new ColumnReference('target', 'id')
        );

        $this::assertEquals($relation, clone $merge->relation);
        $this::assertEquals($joined, clone $merge->using);
        $this::assertEquals($condition, clone $merge->on);
        $this::assertEquals(new MergeWhenMatched(), clone $merge->when[0]);

        $this::assertSame($factory->getParser(), $merge->getParser());
    }

    public function testCreateSelectStatement(): void
    {
        $factory = new StatementFactory();

        $select = $factory->select('foo as newfoo, barsource.bar', 'someschema.foosource, otherschema.barsource');
        $targetList = new TargetList([
            new TargetElement(new ColumnReference('foo'), new Identifier('newfoo')),
            new TargetElement(new ColumnReference('barsource', 'bar'))
        ]);
        $fromList = new FromList([
            new RelationReference(new QualifiedName('someschema', 'foosource')),
            new RelationReference(new QualifiedName('otherschema', 'barsource'))
        ]);

        $this->assertEquals($targetList, clone $select->list);
        $this->assertEquals($fromList, clone $select->from);
        $this->assertSame($factory->getParser(), $select->getParser());

        $select2 = $factory->select([
            'foo as newfoo',
            new TargetElement(new ColumnReference('barsource', 'bar'))
        ]);
        $this->assertEquals($targetList, clone $select2->list);
        $this->assertCount(0, $select2->from);

        $select3 = $factory->select($targetList, clone $fromList);
        $this->assertSame($targetList, $select3->list);
        $this->assertEquals($fromList, clone $select3->from);
    }

    public function testCreateUpdateStatement(): void
    {
        $factory = new StatementFactory();

        $update   = $factory->update('someschema.foo as bar', 'blah = default, blahblah = 42');
        $relation = new UpdateOrDeleteTarget(
            new QualifiedName('someschema', 'foo'),
            new Identifier('bar')
        );
        $setClauseList = new SetClauseList([
            new SingleSetClause(
                new SetTargetElement(new Identifier('blah')),
                new SetToDefault()
            ),
            new SingleSetClause(
                new SetTargetElement(new Identifier('blahblah')),
                new NumericConstant('42')
            )
        ]);

        $this->assertEquals($relation, clone $update->relation);
        $this->assertEquals($setClauseList, clone $update->set);
        $this->assertSame($factory->getParser(), $update->getParser());

        $update2 = $factory->update($relation, $setClauseList);
        $this->assertSame($relation, $update2->relation);
        $this->assertSame($setClauseList, $update2->set);
    }

    public function testCreateValuesStatement(): void
    {
        $factory = new StatementFactory();

        $values = $factory->values("('foo', 42), ('bar', default)");
        $rows   = new RowList([
            [new StringConstant('foo'), new NumericConstant('42')],
            [new StringConstant('bar'), new SetToDefault()]
        ]);

        $this->assertEquals($rows, clone $values->rows);
        $this->assertSame($factory->getParser(), $values->getParser());
    }

    protected function assertStringsEqualIgnoringWhitespace(
        string $expected,
        string $actual,
        string $message = ''
    ): void {
        $this::assertEquals(
            implode(' ', preg_split('/\s+/', trim($expected))),
            implode(' ', preg_split('/\s+/', trim($actual))),
            $message
        );
    }

    /**
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     */
    public function testPDOPrepareCompatibility(): void
    {
        $factory = new StatementFactory(null, null, true);
        $stmt    = $factory->createFromString(<<<'BLAH'
select * 
from foo 
where bar = $$O'really? \ Yes, really$$ 
      and baz ? :whatever
BLAH
        );

        $this->assertStringsEqualIgnoringWhitespace(
            "select * from foo where bar = e'O\\'really? \\\\ Yes, really' and baz ?? :whatever",
            $factory->createFromAST($stmt)->getSql()
        );

        $stmt2 = $factory->createFromString($stmt2Source = <<<'BLAH'
select * 
from foo 
where bar = $$O'really? \ Yes, really$$ 
      and baz ? 'whatever'
BLAH
        );

        $this->assertStringsEqualIgnoringWhitespace($stmt2Source, $factory->createFromAST($stmt2)->getSql());
    }
}
