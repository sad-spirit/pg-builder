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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_wrapper\Connection,
    sad_spirit\pg_wrapper\cache\File,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\StatementFactory,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\SetTargetElement,
    sad_spirit\pg_builder\nodes\SetToDefault,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\lists\CtextRowList,
    sad_spirit\pg_builder\nodes\lists\FromList,
    sad_spirit\pg_builder\nodes\lists\SetTargetList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\range\InsertTarget,
    sad_spirit\pg_builder\nodes\range\RelationReference,
    sad_spirit\pg_builder\nodes\range\UpdateOrDeleteTarget;

/**
 * Unit test for StatementFactory class
 */
class StatementFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreatesDefaultParser()
    {
        $factory = new StatementFactory();
        $this->assertEquals(
            new Parser(new Lexer()),
            $factory->getParser()
        );
    }

    public function testCreatesParserBasedOnConnection()
    {
        if (!TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $cache      = new File(__DIR__ . '/../cache');
        $connection = new Connection(TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING);
        $connection->execute("set standard_conforming_strings = off");
        $connection->setMetadataCache($cache);
        $factory    = new StatementFactory($connection);

        $expected   = new Parser(new Lexer(array('standard_conforming_strings' => false)), $cache);
        $expected->setOperatorPrecedence(
            version_compare(pg_parameter_status($connection->getResource(), 'server_version'), '9.5.0', '>=')
            ? Parser::OPERATOR_PRECEDENCE_CURRENT : Parser::OPERATOR_PRECEDENCE_PRE_9_5
        );

        $this->assertEquals($expected, $factory->getParser());
    }

    public function testSetsParserOnCreatingFromString()
    {
        $factory = new StatementFactory();
        $select  = $factory->createFromString('select foo from bar');
        $this->assertAttributeSame($factory->getParser(), '_parser', $select);
    }

    public function testCreateDeleteStatement()
    {
        $factory = new StatementFactory();

        $delete   = $factory->delete('only foo as bar');
        $relation = new UpdateOrDeleteTarget(
            new QualifiedName(array('foo')), new Identifier('bar'),false
        );
        $this->assertEquals($relation, clone $delete->relation);
        $this->assertAttributeSame($factory->getParser(), '_parser', $delete);

        $delete2 = $factory->delete($relation);
        $this->assertSame($relation, $delete2->relation);
    }

    public function testCreateInsertStatement()
    {
        $factory = new StatementFactory();

        $insert   = $factory->insert('someschema.target as aliaz');
        $target   = new InsertTarget(
            new QualifiedName(array('someschema', 'target')),
            new Identifier('aliaz')
        );
        $this->assertEquals($target, clone $insert->relation);
        $this->assertAttributeSame($factory->getParser(), '_parser', $insert);

        $insert2 = $factory->insert($target);
        $this->assertSame($target, $insert2->relation);
    }

    public function testCreateSelectStatement()
    {
        $factory = new StatementFactory();

        $select = $factory->select('foo as newfoo, barsource.bar', 'someschema.foosource, otherschema.barsource');
        $targetList = new TargetList(array(
            new TargetElement(new ColumnReference(array('foo')), new Identifier('newfoo')),
            new TargetElement(new ColumnReference(array('barsource', 'bar')))
        ));
        $fromList = new FromList(array(
            new RelationReference(new QualifiedName(array('someschema', 'foosource'))),
            new RelationReference(new QualifiedName(array('otherschema', 'barsource')))
        ));

        $this->assertEquals($targetList, clone $select->list);
        $this->assertEquals($fromList, clone $select->from);
        $this->assertAttributeSame($factory->getParser(), '_parser', $select);

        $select2 = $factory->select(array(
            'foo as newfoo',
            new TargetElement(new ColumnReference(array('barsource', 'bar')))
        ));
        $this->assertEquals($targetList, clone $select2->list);
        $this->assertEquals(0, count($select2->from));

        $select3 = $factory->select($targetList, clone $fromList);
        $this->assertSame($targetList, $select3->list);
        $this->assertEquals($fromList, clone $select3->from);
    }

    public function testCreateUpdateStatement()
    {
        $factory = new StatementFactory();

        $update   = $factory->update('someschema.foo as bar', 'blah = default, blahblah = 42');
        $relation = new UpdateOrDeleteTarget(
            new QualifiedName(array('someschema', 'foo')), new Identifier('bar')
        );
        $targetList = new SetTargetList(array(
            new SetTargetElement(new Identifier('blah'), array(), new SetToDefault()),
            new SetTargetElement(new Identifier('blahblah'), array(), new Constant(42))
        ));

        $this->assertEquals($relation, clone $update->relation);
        $this->assertEquals($targetList, clone $update->set);
        $this->assertAttributeSame($factory->getParser(), '_parser', $update);

        $update2 = $factory->update($relation, $targetList);
        $this->assertSame($relation, $update2->relation);
        $this->assertSame($targetList, $update2->set);
    }

    public function testCreateValuesStatement()
    {
        $factory = new StatementFactory();

        $values    = $factory->values("('foo', 42), ('bar', default)");
        $ctextRows = new CtextRowList(array(
            array(new Constant('foo'), new Constant(42)),
            array(new Constant('bar'), new SetToDefault())
        ));

        $this->assertEquals($ctextRows, clone $values->rows);
        $this->assertAttributeSame($factory->getParser(), '_parser', $values);
    }
}