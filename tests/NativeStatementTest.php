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
use sad_spirit\pg_wrapper\{
    Connection,
    PreparedStatement,
    converters\DefaultTypeConverterFactory
};
use sad_spirit\pg_builder\{
    NativeStatement,
    StatementFactory,
    exceptions\InvalidArgumentException,
    exceptions\RuntimeException,
    converters\BuilderSupportDecorator
};

class NativeStatementTest extends TestCase
{
    protected Connection $connection;

    protected StatementFactory $factory;

    protected function setUp(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $this->connection = new Connection(TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING);
        $this->factory    = StatementFactory::forConnection($this->connection);
        $this->connection->setTypeConverterFactory(new BuilderSupportDecorator(
            new DefaultTypeConverterFactory(),
            $this->factory->getParser()
        ));
    }

    public function testNamedParameters(): void
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $this->assertStringContainsString('$1', $native->getSql());

        $result = $native->executeParams($this->connection, ['oid' => 23]);
        $this->assertEquals('int4', $result[0]['typname']);
    }

    public function testMissingNamedParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing parameter name');
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select * from pg_catalog.pg_type where oid = :oid or typname = :name'
        ));
        $native->executeParams($this->connection, ['name' => 'text']);
    }

    public function testUnknownNamedParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown keys');
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $native->executeParams($this->connection, ['oid' => 23, 'name' => 'text']);
    }

    public function testMapInputTypes(): void
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = any(:oid) order by typname'
        ));
        $result = $native->executeParams(
            $this->connection,
            ['oid' => [21, 23]],
            ['oid' => 'int4[]']
        );
        $this->assertEquals('int2', $result[0]['typname']);
    }

    public function testExtractInputTypes(): void
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = any(:oid::integer[]) order by typname'
        ));
        $result = $native->executeParams($this->connection, ['oid' => [21, 23]]);
        $this->assertEquals('int2', $result[0]['typname']);

        /* @var $native2 NativeStatement */
        $native2 = \unserialize(\serialize($native));
        $result2 = $native2->executeParams($this->connection, ['oid' => [25]]);
        $this->assertEquals('text', $result2[0]['typname']);
    }

    public function testExecutePrepared(): void
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $prepared = $native->prepare($this->connection);
        $this->assertInstanceOf(PreparedStatement::class, $prepared);

        $resultOne = $native->executePrepared(['oid' => 21]);
        $this->assertEquals('int2', $resultOne[0]['typname']);

        $resultTwo = $native->executePrepared(['oid' => 23]);
        $this->assertEquals('int4', $resultTwo[0]['typname']);
    }

    public function testExecutePreparedWithExplicitType(): void
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = any(:oid)'
        ));
        $native->prepare($this->connection, ['oid' => 'integer[]']);

        $result = $native->executePrepared(['oid' => [23]]);
        $this->assertEquals('int4', $result[0]['typname']);
    }

    public function testCannotExecutePreparedWithoutPrepare(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('prepare() should be called first');
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $native->executePrepared(['oid' => 23]);
    }

    public function testPreparedStatementIsNotSerialized(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('prepare() should be called first');
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $native->prepare($this->connection);

        /* @var $native2 NativeStatement */
        $native2 = \unserialize(\serialize($native));
        $native2->executePrepared(['oid' => 23]);
    }

    public function testPrepareWithResultTypes(): void
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select row(typname, typbyval, typcategory) as needstype from pg_catalog.pg_type where oid = :oid'
        ));
        $native->prepare($this->connection, [], ['needstype' => ['text', 'bool', 'text']]);
        $result = $native->executePrepared(['oid' => 23]);
        $this->assertEquals(
            ['int4', true, 'N'],
            $result[0]['needstype']
        );
    }
}
