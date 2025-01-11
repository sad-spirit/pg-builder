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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Lexer,
    NativeStatement,
    Parser,
    converters\BuilderSupportDecorator,
    exceptions\InvalidArgumentException,
    nodes\QualifiedName,
    nodes\TypeName
};
use sad_spirit\pg_wrapper\{
    Connection,
    TypeConverter
};
use sad_spirit\pg_wrapper\converters\{
    DefaultTypeConverterFactory,
    IntegerConverter,
    NumericConverter,
    StringConverter,
    containers\ArrayConverter,
    datetime\IntervalConverter,
    datetime\TimeStampTzConverter
};

/**
 * Unit test for BuilderSupportDecorator
 */
class BuilderSupportDecoratorTest extends TestCase
{
    protected ?BuilderSupportDecorator $factory;

    protected function setUp(): void
    {
        $this->factory = new BuilderSupportDecorator(
            new DefaultTypeConverterFactory(),
            new Parser(new Lexer())
        );
    }

    public function testCreateTypeNameNode(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_BUILDER_CONNECTION_STRING);
        /** @psalm-suppress PossiblyNullArgument */
        $connection->setTypeConverterFactory($this->factory);

        $this->assertEquals(
            new TypeName(new QualifiedName('int4')),
            $this->factory->createTypeNameNodeForOid(23)
        );
    }

    public function testGetConverterForTypeNameNode(): void
    {
        $this->assertEquals(
            new IntegerConverter(),
            $this->factory->getConverterForTypeSpecification(new TypeName(new QualifiedName('int4')))
        );
    }

    #[DataProvider('complexTypeNamesProvider')]
    public function testParseComplexTypeNames(string $typeName, TypeConverter $converter): void
    {
        $this->assertEquals($converter, $this->factory->getConverterForTypeSpecification($typeName));
    }

    public static function complexTypeNamesProvider(): array
    {
        return [
            ["decimal(1,2)",                   new NumericConverter()],
            ["timestamp (0) with time zone",   new TimeStampTzConverter()],
            ["national character varying(13)", new StringConverter()],
            ["postgres.pg_catalog.int4 array", new ArrayConverter(new IntegerConverter())],
            ["interval hour to second(10)",    new IntervalConverter()]
        ];
    }

    /**
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     *
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $paramTypes
     * @param string[]|string      $expected
     */
    #[DataProvider('parametersProvider')]
    public function testConvertParameters(array $parameters, array $paramTypes, string|array $expected): void
    {
        $native = new NativeStatement(
            'select * from foo where bar = :bar and baz > :baz',
            [
                new TypeName(new QualifiedName('point')),
                null
            ],
            [
                'bar' => 0,
                'baz' => 1
            ]
        );

        if (\is_array($expected)) {
            $this::assertEquals($expected, $this->factory->convertParameters($native, $parameters, $paramTypes));
        } else {
            $this::expectException(InvalidArgumentException::class);
            $this::expectExceptionMessage($expected);
            $this->factory->convertParameters($native, $parameters, $paramTypes);
        }
    }

    public static function parametersProvider(): array
    {
        return [
            [
                ['bar' => [1, 2], 'baz' => 3],
                ['baz' => 'interval'],
                ['bar' => '(1,2)', 'baz' => '3 seconds']
            ],
            [
                ['bar' => [3, 4], 'baz' => new \DateInterval('PT3M')],
                ['bar' => 'integer array'],
                ['bar' => '{"3","4"}', 'baz' => 'PT3M']
            ],
            [
                ['bar' => [5, 6]],
                [],
                "Missing parameter name 'baz'"
            ],
            [
                ['bar' => [7, 8], 'baz' => 9, 'quux' => 10],
                [],
                "Unknown keys in parameters array: 'quux'"
            ]
        ];
    }
}
