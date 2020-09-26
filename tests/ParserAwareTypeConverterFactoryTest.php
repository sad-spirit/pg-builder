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

use sad_spirit\pg_builder\converters\ParserAwareTypeConverterFactory,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\TypeName,
    sad_spirit\pg_wrapper\Connection,
    sad_spirit\pg_wrapper\TypeConverter,
    sad_spirit\pg_wrapper\converters\containers\ArrayConverter,
    sad_spirit\pg_wrapper\converters\datetime\IntervalConverter,
    sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter,
    sad_spirit\pg_wrapper\converters\IntegerConverter,
    sad_spirit\pg_wrapper\converters\NumericConverter,
    sad_spirit\pg_wrapper\converters\StringConverter;

/**
 * Tests functionality added by ParserAwareTypeConverterFactory
 */
class ParserAwareTypeConverterFactoryTest extends \PHPUnit\Framework\TestCase
{
    /** @var ParserAwareTypeConverterFactory */
    protected $factory;

    protected function setUp(): void
    {
        $this->factory = new ParserAwareTypeConverterFactory(new Parser(new Lexer()));
    }

    public function testCreateTypeNameNode()
    {
        if (!TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING);
        $connection->setTypeConverterFactory($this->factory);

        $this->assertEquals(
            new TypeName(new QualifiedName(array('int4'))),
            $this->factory->createTypeNameNodeForOid(23)
        );
    }

    public function testGetConverterForTypeNameNode()
    {
        $this->assertEquals(
            new IntegerConverter(),
            $this->factory->getConverter(new TypeName(new QualifiedName(array('int4'))))
        );
    }

    /**
     * @param string        $typeName
     * @param TypeConverter $converter
     * @dataProvider complexTypeNamesProvider
     */
    public function testParseComplexTypeNames($typeName, TypeConverter $converter)
    {
        $this->assertEquals($converter, $this->factory->getConverter($typeName));
    }

    public function complexTypeNamesProvider()
    {
        return array(
            array("decimal(1,2)",                   new NumericConverter()),
            array("timestamp (0) with time zone",   new TimeStampTzConverter()),
            array("national character varying(13)", new StringConverter()),
            array("postgres.pg_catalog.int4 array", new ArrayConverter(new IntegerConverter())),
            array("interval hour to second(10)",    new IntervalConverter())
        );
    }
}