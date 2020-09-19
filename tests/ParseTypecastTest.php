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

use sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\lists\TypeModifierList,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\TypeName,
    sad_spirit\pg_builder\nodes\IntervalTypeName,
    sad_spirit\pg_builder\nodes\expressions\TypecastExpression,
    sad_spirit\pg_builder\nodes\QualifiedName;

/**
 * Tests parsing all possible types of typecast expressions
 */
class ParseTypecastTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testTypecastOperator()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo.bar::baz, foo::bar.baz, foo::bar::baz(666), foo::int array, foo::int[]
QRY
        );
        $arrayOfInt = new TypeName(new QualifiedName(array('pg_catalog', 'int4')));
        $arrayOfInt->setBounds(array(-1));

        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(
                    new ColumnReference(array('foo', 'bar')),
                    new TypeName(new QualifiedName(array('baz')))
                ),
                new TypecastExpression(
                    new ColumnReference(array('foo')),
                    new TypeName(new QualifiedName(array('bar', 'baz')))
                ),
                new TypecastExpression(
                    new TypecastExpression(
                        new ColumnReference(array(new Identifier('foo'))),
                        new TypeName(new QualifiedName(array('bar')))
                    ),
                    new TypeName(
                        new QualifiedName(array('baz')),
                        new TypeModifierList(array(new Constant(666)))
                    )
                ),
                new TypecastExpression(new ColumnReference(array('foo')), clone $arrayOfInt),
                new TypecastExpression(new ColumnReference(array('foo')), clone $arrayOfInt)
            )),
            $list
        );
    }

    public function testNumericTypes()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast (foo as int), cast(foo as integer), cast(foo as smallint), foo::bigint,
    foo::real, foo::float(20), cast(foo as float(40)), cast (foo as double precision),
    foo::decimal(1,2), foo::dec(3), cast (foo as numeric), foo::boolean
QRY
        );
        $foo = new ColumnReference(array('foo'));
        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'int4')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'int4')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'int2')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'int8')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'float4')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'float4')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'float8')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'float8')))),
                new TypecastExpression(clone $foo, new TypeName(
                    new QualifiedName(array('pg_catalog', 'numeric')),
                    new TypeModifierList(array(new Constant(1), new Constant(2)))
                )),
                new TypecastExpression(clone $foo, new TypeName(
                    new QualifiedName(array('pg_catalog', 'numeric')),
                    new TypeModifierList(array(new Constant(3)))
                )),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'numeric')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'bool'))))
            )),
            $list
        );
    }

    public function testBitTypes()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo::bit, cast(foo as bit varying), foo::bit varying(10), cast (foo as bit(10))
QRY
        );
        $foo   = new ColumnReference(array('foo'));
        $mod10 = new TypeModifierList(array(new Constant(10)));
        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(clone $foo, new TypeName(
                    new QualifiedName(array('pg_catalog', 'bit')),
                    new TypeModifierList(array(new Constant(1)))
                )),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'varbit')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'varbit')), clone $mod10)),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'bit')), clone $mod10))
            )),
            $list
        );
    }

    public function testCharacterTypes()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast(blah as character), blah::char varying, cast(blah as character varying(13)),
    blah::char(13), blah::varchar, blah::nchar, cast(blah as national character varying(13))
QRY
        );
        $blah  = new ColumnReference(array('blah'));
        $mod13 = new TypeModifierList(array(new Constant(13)));
        $mod1  = new TypeModifierList(array(new Constant(1)));

        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(clone $blah, new TypeName(new QualifiedName(array('pg_catalog', 'bpchar')), clone $mod1)),
                new TypecastExpression(clone $blah, new TypeName(new QualifiedName(array('pg_catalog', 'varchar')))),
                new TypecastExpression(clone $blah, new TypeName(new QualifiedName(array('pg_catalog', 'varchar')), clone $mod13)),
                new TypecastExpression(clone $blah, new TypeName(new QualifiedName(array('pg_catalog', 'bpchar')), clone $mod13)),
                new TypecastExpression(clone $blah, new TypeName(new QualifiedName(array('pg_catalog', 'varchar')))),
                new TypecastExpression(clone $blah, new TypeName(new QualifiedName(array('pg_catalog', 'bpchar')), clone $mod1)),
                new TypecastExpression(clone $blah, new TypeName(new QualifiedName(array('pg_catalog', 'varchar')), clone $mod13)),
            )),
            $list
        );
    }

    public function testDateTimeTypes()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo::time, foo::timestamp(3), cast(foo as time with time zone), foo::timestamp without time zone,
    cast (foo as timestamp(3) with time zone)
QRY
        );
        $foo  = new ColumnReference(array('foo'));
        $mod3 = new TypeModifierList(array(new Constant(3)));
        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'time')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'timestamp')), clone $mod3)),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'timetz')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'timestamp')))),
                new TypecastExpression(clone $foo, new TypeName(new QualifiedName(array('pg_catalog', 'timestamptz')), clone $mod3)),
            )),
            $list
        );
    }

    public function testIntervalType()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast (foo as interval), foo::interval(10), foo::interval hour to second(10)
QRY
        );

        $foo    = new ColumnReference(array('foo'));
        $mod10  = new TypeModifierList(array(new Constant(10)));
        $masked = new IntervalTypeName($mod10);
        $masked->setMask('hour to second');

        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(clone $foo, new IntervalTypeName()),
                new TypecastExpression(clone $foo, new IntervalTypeName(clone $mod10)),
                new TypecastExpression(clone $foo, $masked)
            )),
            $list
        );
    }

    public function testComplexTypes()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    cast (foo as setof text), cast (foo as text array[5]), cast (foo as text[5])
QRY
        );

        $foo    = new ColumnReference(array('foo'));
        $array5 = new TypeName(new QualifiedName(array('text')));
        $array5->setBounds(array(5));
        $setof  = new TypeName(new QualifiedName(array('text')));
        $setof->setSetOf(true);

        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(clone $foo, $setof),
                new TypecastExpression(clone $foo, clone $array5),
                new TypecastExpression(clone $foo, clone $array5)
            )),
            $list
        );
    }

    public function testGenericTypeModifiers()
    {
        $this->assertEquals(
            new TypecastExpression(
                new Constant('a value'),
                new TypeName(
                    new QualifiedName(array('foo', 'bar', 'baz')),
                    new TypeModifierList(array(new Identifier('quux'), new Constant('xyzzy'), new Constant(42)))
                )
            ),
            $this->parser->parseExpression("cast('a value' as foo.bar.baz(quux, 'xyzzy', 42))")
        );
    }

    public function testLeadingTypecast()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    double precision 'a value', national char varying 'a value', varchar(10) 'a value',
    char 'a value', bit 'a value', bit varying (10) 'a value', time (10) with time zone 'a value',
    timestamp without time zone 'a value', interval 'a value' minute to second (10), quux.xyzzy 'a value'
QRY
        );
        $val      = new Constant('a value');
        $mod10    = new TypeModifierList(array(new Constant(10)));
        $interval = new IntervalTypeName($mod10);
        $interval->setMask('minute to second');
        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'float8')))),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'varchar')))),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'varchar')), clone $mod10)),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'bpchar')))),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'bit')))),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'varbit')), clone $mod10)),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'timetz')), clone $mod10)),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('pg_catalog', 'timestamp')))),
                new TypecastExpression(clone $val, $interval),
                new TypecastExpression(clone $val, new TypeName(new QualifiedName(array('quux', 'xyzzy'))))
            )),
            $list
        );
    }

    /**
     * @dataProvider getInvalidTypeSpecifications
     */
    public function testInvalidTypeSpecification($expression, $message)
    {
        $this->setExpectedException(
            'sad_spirit\pg_builder\exceptions\SyntaxException',
            $message
        );
        $this->parser->parseExpression($expression);
    }

    public function getInvalidTypeSpecifications()
    {
        return array(
            array(
                'cast(foo as float(0))',
                'Precision for type float must be at least 1 bit'
            ),
            array(
                'cast(foo as float(666))',
                'Precision for type float must be less than 54 bits'
            ),
            array(
                'cast(foo as numeric(1,2,3))',
                "Unexpected special character ','"
            ),
            array(
                'cast(foo as bit(1, 2))',
                "Unexpected special character ','"
            ),
            array(
                "cast(foo as 'bar')",
                'Expecting type name'
            ),
            array(
                "cast(foo as select)",
                'Expecting type name'
            ),
            array(
                "cast('bar' as foo())",
                'Expecting a constant or an identifier'
            ),
            array(
                "foo() 'bar'",
                'Unexpected string literal'
            ),
            array(
                "cast (foo as interval day to hour (5))",
                "Unexpected special character '('"
            )
        );
    }
}