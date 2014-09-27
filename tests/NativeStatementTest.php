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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_wrapper\Connection,
    sad_spirit\pg_builder\NativeStatement,
    sad_spirit\pg_builder\StatementFactory;

class NativeStatementTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var StatementFactory
     */
    protected $factory;

    public function setUp()
    {
        if (!TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $this->connection = new Connection(TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING);
        $this->factory    = new StatementFactory($this->connection);
    }

    public function testNamedParameters()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $this->assertContains('$1', $native->getSql());

        $result = $native->executeParams($this->connection, array('oid' => 23));
        $this->assertEquals('int4', $result[0]['typname']);
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     * @expectedExceptionMessage Missing parameter name
     */
    public function testMissingNamedParameter()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select * from pg_catalog.pg_type where oid = :oid or typname = :name'
        ));
        $native->executeParams($this->connection, array('name' => 'text'));
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\InvalidArgumentException
     * @expectedExceptionMessage Unknown keys
     */
    public function testUnknownNamedParameter()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $native->executeParams($this->connection, array('oid' => 23, 'name' => 'text'));
    }

    public function testMapInputTypes()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = any(:oid) order by typname'
        ));
        $result = $native->executeParams(
            $this->connection, array('oid' => array(21, 23)), array('oid' => 'int4[]')
        );
        $this->assertEquals('int2', $result[0]['typname']);
    }

    public function testExtractInputTypes()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = any(:oid::integer[]) order by typname'
        ));
        $result = $native->executeParams($this->connection, array('oid' => array(21, 23)));
        $this->assertEquals('int2', $result[0]['typname']);

        /* @var $native2 NativeStatement */
        $native2 = unserialize(serialize($native));
        $result2 = $native2->executeParams($this->connection, array('oid' => array(25)));
        $this->assertEquals('text', $result2[0]['typname']);
    }

    public function testExecutePrepared()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $prepared = $native->prepare($this->connection);
        $this->assertInstanceOf('\sad_spirit\pg_wrapper\PreparedStatement', $prepared);

        $resultOne = $native->executePrepared(array('oid' => 21));
        $this->assertEquals('int2', $resultOne[0]['typname']);

        $resultTwo = $native->executePrepared(array('oid' => 23));
        $this->assertEquals('int4', $resultTwo[0]['typname']);
    }

    public function testExecutePreparedWithExplicitType()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = any(:oid)'
        ));
        $native->prepare($this->connection, array('oid' => 'integer[]'));

        $result = $native->executePrepared(array('oid' => array(23)));
        $this->assertEquals('int4', $result[0]['typname']);
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\RuntimeException
     * @expectedExceptionMessage prepare() should be called first
     */
    public function testCannotExecutePreparedWithoutPrepare()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $native->executePrepared(array('oid' => 23));
    }

    /**
     * @expectedException \sad_spirit\pg_wrapper\exceptions\RuntimeException
     * @expectedExceptionMessage prepare() should be called first
     */
    public function testPreparedStatementIsNotSerialized()
    {
        $native = $this->factory->createFromAST($this->factory->createFromString(
            'select typname from pg_catalog.pg_type where oid = :oid'
        ));
        $native->prepare($this->connection);

        /* @var $native2 NativeStatement */
        $native2 = unserialize(serialize($native));
        $native2->executePrepared(array('oid' => 23));
    }
}