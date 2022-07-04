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
    Lexer,
    Parser,
    exceptions\SyntaxException
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    expressions\NumericConstant,
    expressions\StringConstant,
    Identifier,
    QualifiedName,
    TypeName,
    IntervalTypeName,
    expressions\TypecastExpression,
    lists\ExpressionList,
    lists\TypeModifierList
};

/**
 * Tests parsing all possible types of typecast expressions
 */
class ParseTypecastTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testTypecastOperator(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo.bar::baz, foo::bar.baz, foo::bar::baz(666), foo::int array, foo::int[]
QRY
        );
        $arrayOfInt = new TypeName(new QualifiedName('pg_catalog', 'int4'));
        $arrayOfInt->setBounds([-1]);

        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(
                    new ColumnReference('foo', 'bar'),
                    new TypeName(new QualifiedName('baz'))
                ),
                new TypecastExpression(
                    new ColumnReference('foo'),
                    new TypeName(new QualifiedName('bar', 'baz'))
                ),
                new TypecastExpression(
                    new TypecastExpression(
                        new ColumnReference(new Identifier('foo')),
                        new TypeName(new QualifiedName('bar'))
                    ),
                    new TypeName(
                        new QualifiedName('baz'),
                        new TypeModifierList([new NumericConstant('666')])
                    )
                ),
                new TypecastExpression(new ColumnReference('foo'), clone $arrayOfInt),
                new TypecastExpression(new ColumnReference('foo'), clone $arrayOfInt)
            ]),
            $list
        );
    }

    public function testNumericTypes(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast (foo as int), cast(foo as integer), cast(foo as smallint), foo::bigint,
    foo::real, foo::float(20), cast(foo as float(40)), cast (foo as double precision),
    foo::decimal(1,2), foo::dec(3), cast (foo as numeric), foo::boolean
QRY
        );
        $foo = new ColumnReference('foo');
        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'int4'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'int4'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'int2'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'int8'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'float4'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'float4'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'float8'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'float8'))),
                new TypecastExpression(clone $foo, new TypeName(
                    new QualifiedName('pg_catalog', 'numeric'),
                    new TypeModifierList([new NumericConstant('1'), new NumericConstant('2')])
                )),
                new TypecastExpression(clone $foo, new TypeName(
                    new QualifiedName('pg_catalog', 'numeric'),
                    new TypeModifierList([new NumericConstant('3')])
                )),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'numeric'))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'bool')))
            ]),
            $list
        );
    }

    public function testBitTypes(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo::bit, cast(foo as bit varying), foo::bit varying(10), cast (foo as bit(10))
QRY
        );
        $foo   = new ColumnReference('foo');
        $mod10 = new TypeModifierList([new NumericConstant('10')]);
        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(clone $foo, new TypeName(
                    new QualifiedName('pg_catalog', 'bit'),
                    new TypeModifierList([new NumericConstant('1')])
                )),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'varbit'))),
                new TypecastExpression(
                    clone $foo,
                    new TypeName(new QualifiedName('pg_catalog', 'varbit'), clone $mod10)
                ),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName('pg_catalog', 'bit'), clone $mod10))
            ]),
            $list
        );
    }

    public function testCharacterTypes(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast(blah as character), blah::char varying, cast(blah as character varying(13)),
    blah::char(13), blah::varchar, blah::nchar, cast(blah as national character varying(13)),
    blah::json
QRY
        );
        $blah  = new ColumnReference('blah');
        $mod13 = new TypeModifierList([new NumericConstant('13')]);
        $mod1  = new TypeModifierList([new NumericConstant('1')]);

        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'bpchar'), clone $mod1)
                ),
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'varchar'))
                ),
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'varchar'), clone $mod13)
                ),
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'bpchar'), clone $mod13)
                ),
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'varchar'))
                ),
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'bpchar'), clone $mod1)
                ),
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'varchar'), clone $mod13)
                ),
                new TypecastExpression(
                    clone $blah,
                    new TypeName(new QualifiedName('pg_catalog', 'json'))
                )
            ]),
            $list
        );
    }

    public function testDateTimeTypes(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo::time, foo::timestamp(3), cast(foo as time with time zone), foo::timestamp without time zone,
    cast (foo as timestamp(3) with time zone)
QRY
        );
        $foo  = new ColumnReference('foo');
        $mod3 = new TypeModifierList([new NumericConstant('3')]);
        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(
                    clone $foo,
                    new TypeName(new QualifiedName('pg_catalog', 'time'))
                ),
                new TypecastExpression(
                    clone $foo,
                    new TypeName(new QualifiedName('pg_catalog', 'timestamp'), clone $mod3)
                ),
                new TypecastExpression(
                    clone $foo,
                    new TypeName(new QualifiedName('pg_catalog', 'timetz'))
                ),
                new TypecastExpression(
                    clone $foo,
                    new TypeName(new QualifiedName('pg_catalog', 'timestamp'))
                ),
                new TypecastExpression(
                    clone $foo,
                    new TypeName(new QualifiedName('pg_catalog', 'timestamptz'), clone $mod3)
                ),
            ]),
            $list
        );
    }

    public function testIntervalType(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast (foo as interval), foo::interval(10), foo::interval hour to second(10)
QRY
        );

        $foo    = new ColumnReference('foo');
        $mod10  = new TypeModifierList([new NumericConstant('10')]);
        $masked = new IntervalTypeName($mod10);
        $masked->setMask('hour to second');

        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(clone $foo, new IntervalTypeName()),
                new TypecastExpression(clone $foo, new IntervalTypeName(clone $mod10)),
                new TypecastExpression(clone $foo, $masked)
            ]),
            $list
        );
    }

    public function testComplexTypes(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast (foo as setof text), cast (foo as text array[5]), cast (foo as text[5])
QRY
        );

        $foo    = new ColumnReference('foo');
        $array5 = new TypeName(new QualifiedName('text'));
        $array5->setBounds([5]);

        $setof  = new TypeName(new QualifiedName('text'));
        $setof->setSetOf(true);

        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(clone $foo, $setof),
                new TypecastExpression(clone $foo, clone $array5),
                new TypecastExpression(clone $foo, clone $array5)
            ]),
            $list
        );
    }

    public function testGenericTypeModifiers(): void
    {
        $this->assertEquals(
            new TypecastExpression(
                new StringConstant('a value'),
                new TypeName(
                    new QualifiedName('foo', 'bar', 'baz'),
                    new TypeModifierList([
                        new Identifier('quux'), new StringConstant('xyzzy'), new NumericConstant('42')
                    ])
                )
            ),
            $this->parser->parseExpression("cast('a value' as foo.bar.baz(quux, 'xyzzy', 42))")
        );
    }

    public function testLeadingTypecast(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    double precision 'a value', national char varying 'a value', varchar(10) 'a value',
    char 'a value', bit 'a value', bit varying (10) 'a value', time (10) with time zone 'a value',
    timestamp without time zone 'a value', interval 'a value' minute to second (10), interval (10) 'a value', 
    quux.xyzzy 'a value', blah.blah (10) 'a value', json '{"foo":"bar"}'
QRY
        );
        $val      = new StringConstant('a value');
        $mod10    = new TypeModifierList([new NumericConstant('10')]);
        $interval = new IntervalTypeName($mod10);
        $interval->setMask('minute to second');
        $this->assertEquals(
            new ExpressionList([
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'float8'))
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'varchar'))
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'varchar'), clone $mod10)
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'bpchar'))
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'bit'))
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'varbit'), clone $mod10)
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'timetz'), clone $mod10)
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('pg_catalog', 'timestamp'))
                ),
                new TypecastExpression(
                    clone $val,
                    $interval
                ),
                new TypecastExpression(
                    clone $val,
                    new IntervalTypeName(clone $mod10)
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('quux', 'xyzzy'))
                ),
                new TypecastExpression(
                    clone $val,
                    new TypeName(new QualifiedName('blah', 'blah'), clone $mod10)
                ),
                new TypecastExpression(
                    new StringConstant('{"foo":"bar"}'),
                    new TypeName(new QualifiedName('pg_catalog', 'json'))
                )
            ]),
            $list
        );
    }

    /**
     * @dataProvider getInvalidTypeSpecifications
     * @param string $expression
     * @param string $message
     */
    public function testInvalidTypeSpecification(string $expression, string $message): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($expression);
    }

    public function getInvalidTypeSpecifications(): array
    {
        return [
            [
                'cast(foo as float(0))',
                'Precision for type float must be at least 1 bit'
            ],
            [
                'cast(foo as float(666))',
                'Precision for type float must be less than 54 bits'
            ],
            [
                'cast(foo as numeric(1,2,3))',
                "Unexpected special character ','"
            ],
            [
                'cast(foo as bit(1, 2))',
                "Unexpected special character ','"
            ],
            [
                "cast(foo as 'bar')",
                'Expecting type name'
            ],
            [
                "cast(foo as select)",
                'Expecting type name'
            ],
            [
                "cast('bar' as foo())",
                'Expecting a constant or an identifier'
            ],
            [
                "foo() 'bar'",
                'Unexpected string literal'
            ],
            [
                "cast (foo as interval day to hour (5))",
                "Unexpected special character '('"
            ]
        ];
    }
}
