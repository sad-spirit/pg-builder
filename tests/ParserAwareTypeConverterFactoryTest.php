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

use sad_spirit\pg_builder\converters\ParserAwareTypeConverterFactory;
use sad_spirit\pg_builder\Lexer;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_builder\nodes\TypeName;
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\TypeConverter;
use sad_spirit\pg_wrapper\converters\containers\ArrayConverter;
use sad_spirit\pg_wrapper\converters\datetime\IntervalConverter;
use sad_spirit\pg_wrapper\converters\datetime\TimeStampTzConverter;
use sad_spirit\pg_wrapper\converters\IntegerConverter;
use sad_spirit\pg_wrapper\converters\NumericConverter;
use sad_spirit\pg_wrapper\converters\StringConverter;

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
            new TypeName(new QualifiedName(['int4'])),
            $this->factory->createTypeNameNodeForOid(23)
        );
    }

    public function testGetConverterForTypeNameNode()
    {
        $this->assertEquals(
            new IntegerConverter(),
            $this->factory->getConverterForTypeSpecification(new TypeName(new QualifiedName(['int4'])))
        );
    }

    /**
     * @param string        $typeName
     * @param TypeConverter $converter
     * @dataProvider complexTypeNamesProvider
     */
    public function testParseComplexTypeNames($typeName, TypeConverter $converter)
    {
        $this->assertEquals($converter, $this->factory->getConverterForTypeSpecification($typeName));
    }

    public function complexTypeNamesProvider()
    {
        return [
            ["decimal(1,2)",                   new NumericConverter()],
            ["timestamp (0) with time zone",   new TimeStampTzConverter()],
            ["national character varying(13)", new StringConverter()],
            ["postgres.pg_catalog.int4 array", new ArrayConverter(new IntegerConverter())],
            ["interval hour to second(10)",    new IntervalConverter()]
        ];
    }
}
