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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\OrderByElement,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\Star,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\TypeName,
    sad_spirit\pg_builder\nodes\WindowDefinition,
    sad_spirit\pg_builder\nodes\WindowFrameBound,
    sad_spirit\pg_builder\nodes\WindowFrameClause,
    sad_spirit\pg_builder\nodes\expressions\FunctionExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\expressions\TypecastExpression,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\lists\FunctionArgumentList,
    sad_spirit\pg_builder\nodes\lists\OrderByList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\TypeModifierList,
    sad_spirit\pg_builder\nodes\xml\XmlElement,
    sad_spirit\pg_builder\nodes\xml\XmlForest,
    sad_spirit\pg_builder\nodes\xml\XmlParse,
    sad_spirit\pg_builder\nodes\xml\XmlPi,
    sad_spirit\pg_builder\nodes\xml\XmlRoot,
    sad_spirit\pg_builder\nodes\xml\XmlSerialize;

/**
 * Tests parsing all possible function calls and function-like constructs
 */
class ParseFunctionCallTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testNoParenthesesFunctions()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    current_date, current_role, current_user, session_user, user, current_catalog, current_schema
QRY
        );
        $expected = array(
            new TypecastExpression(
                new Constant('now'),
                new TypeName(new QualifiedName(array('pg_catalog', 'date')))
            )
        );
        foreach (array('current_user', 'current_user', 'session_user', 'current_user',
                       'current_database', 'current_schema') as $fn
        ) {
            $expected[] = new FunctionExpression(new QualifiedName(array('pg_catalog', $fn)));
        }

        $this->assertEquals(new ExpressionList($expected), $list);
    }

    public function testOptionalParenthesesFunctions()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    current_time, current_timestamp(1), localtime(2), localtimestamp
QRY
        );

        $this->assertEquals(
            new ExpressionList(array(
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(new QualifiedName(array('pg_catalog', 'timetz')))
                ),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(
                        new QualifiedName(array('pg_catalog', 'timestamptz')),
                        new TypeModifierList(array(new Constant(1)))
                    )
                ),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(
                        new QualifiedName(array('pg_catalog', 'time')),
                        new TypeModifierList(array(new Constant(2)))
                    )
                ),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(new QualifiedName(array('pg_catalog', 'timestamp')))
                )
            )),
            $list
        );

        $this->setExpectedException(
            'sad_spirit\pg_builder\exceptions\SyntaxException',
            'expecting integer literal'
        );
        $this->parser->parseExpressionList('current_time(foo)');
    }

    public function testExtract()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    extract(epoch from foo), extract(minute from bar), extract('whatever' from baz)
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'date_part')),
                    new FunctionArgumentList(array(new Constant('epoch'), new ColumnReference(array('foo'))))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'date_part')),
                    new FunctionArgumentList(array(new Constant('minute'), new ColumnReference(array('bar'))))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'date_part')),
                    new FunctionArgumentList(array(new Constant('whatever'), new ColumnReference(array('baz'))))
                )
            )),
            $list
        );
    }

    public function testOverlay()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    overlay('fooxxxbaz' placing 'bar' from 3 for 3), overlay('adc' placing 'b' from 2)
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'overlay')),
                    new FunctionArgumentList(array(
                        new Constant('fooxxxbaz'), new Constant('bar'), new Constant(3), new Constant(3)
                    ))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'overlay')),
                    new FunctionArgumentList(array(
                        new Constant('adc'), new Constant('b'), new Constant(2)
                    ))
                )
            )),
            $list
        );
    }

    public function testPosition()
    {
        $this->assertEquals(
            new FunctionExpression(
                new QualifiedName(array('pg_catalog', 'position')),
                new FunctionArgumentList(array(new Constant('foobar'), new Constant('a')))
            ),
            $this->parser->parseExpression("position('a' in 'foobar')")
        );
    }

    public function testSubstring()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    substring('foobar', 2, 3), substring('foobar' from 2 for 3), substring('foobar' for 3 from 2),
    substring('foobar' for 3)
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'substring')),
                    new FunctionArgumentList(array(new Constant('foobar'), new Constant(2), new Constant(3)))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'substring')),
                    new FunctionArgumentList(array(new Constant('foobar'), new Constant(2), new Constant(3)))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'substring')),
                    new FunctionArgumentList(array(new Constant('foobar'), new Constant(2), new Constant(3)))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'substring')),
                    new FunctionArgumentList(array(new Constant('foobar'), new Constant(1), new Constant(3)))
                )
            )),
            $list
        );
    }

    public function testTrim()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    trim(from ' foo '), trim(leading '_' from '_foo_'), trim(trailing from 'foo '), trim(trailing from 'foo', 'o')
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'btrim')),
                    new FunctionArgumentList(array(new Constant(' foo ')))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'ltrim')),
                    new FunctionArgumentList(array(new Constant('_foo_'), new Constant('_')))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'rtrim')),
                    new FunctionArgumentList(array(new Constant('foo ')))
                ),
                new FunctionExpression(
                    new QualifiedName(array('pg_catalog', 'rtrim')),
                    new FunctionArgumentList(array(new Constant('foo'), new Constant('o')))
                ),
            )),
            $list
        );
    }

    public function testNullif()
    {
        $this->assertEquals(
            new FunctionExpression(
                'nullif',
                new FunctionArgumentList(array(new ColumnReference(array('a')), new Constant('b')))
            ),
            $this->parser->parseExpression("nullif(a, 'b') ")
        );

        $this->setExpectedException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->parser->parseExpression('nullif(a, b, c)');
    }

    public function testXmlElement()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    xmlelement(name foo, bar, 'content'), xmlelement(name blah, xmlattributes(baz, quux as xyzzy), 'content')
QRY
        );

        $this->assertEquals(
            new ExpressionList(array(
                new XmlElement(
                    new Identifier('foo'),
                    null,
                    new ExpressionList(array(new ColumnReference(array('bar')), new Constant('content')))
                ),
                new XmlElement(
                    new Identifier('blah'),
                    new TargetList(array(
                        new TargetElement(new ColumnReference(array('baz'))),
                        new TargetElement(new ColumnReference(array('quux')), new Identifier('xyzzy'))
                    )),
                    new ExpressionList(array(new Constant('content')))
                )
            )),
            $list
        );
    }

    public function testXmlExists()
    {
        $this->assertEquals(
            new FunctionExpression(
                new QualifiedName(array('pg_catalog', 'xmlexists')),
                new FunctionArgumentList(array(new Constant("//foo[text() = 'bar']"), new Constant('<blah><foo>bar</foo></blah>')))
            ),
            $this->parser->parseExpression(
                "xmlexists('//foo[text() = ''bar'']' passing by ref '<blah><foo>bar</foo></blah>')"
            )
        );
    }

    public function testXmlForest()
    {
        $this->assertEquals(
            new XmlForest(array(
                new TargetElement(new ColumnReference(array('foo'))),
                new TargetElement(new Constant('bar'), new Identifier('baz'))
            )),
            $this->parser->parseExpression("xmlforest(foo, 'bar' as baz)")
        );
    }

    public function testXmlParse()
    {
        $this->assertEquals(
            new XmlParse('document', new ColumnReference(array('xml', 'doc')), true),
            $this->parser->parseExpression("xmlparse(document xml.doc preserve whitespace)")
        );
    }

    public function testXmlPi()
    {
        $this->assertEquals(
            new XmlPi(new Identifier('php'), new Constant("echo 'Hello world!';")),
            $this->parser->parseExpression("xmlpi(name php, 'echo ''Hello world!'';')")
        );
    }

    public function testXmlRoot()
    {
        $this->assertEquals(
            new XmlRoot(new ColumnReference(array('doc')), new Constant('1.2'), 'yes'),
            $this->parser->parseExpression("xmlroot(doc, version '1.2', standalone yes)")
        );
    }

    public function testXmlSerialize()
    {
        $this->assertEquals(
            new XmlSerialize(
                'document',
                new ColumnReference(array('foo')),
                new TypeName(new QualifiedName(array('pg_catalog', 'text')))
            ),
            $this->parser->parseExpression('xmlserialize(document foo as pg_catalog.text)')
        );
    }

    public function testExplicitlyKnownFunctionNames()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    coalesce(a, 'b'), greatest('c', d), least(e, f), xmlconcat(x, m, l)
QRY
        );

        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    'coalesce',
                    new FunctionArgumentList(array(new ColumnReference(array('a')), new Constant('b')))
                ),
                new FunctionExpression(
                    'greatest',
                    new FunctionArgumentList(array(new Constant('c'), new ColumnReference(array('d'))))
                ),
                new FunctionExpression(
                    'least',
                    new FunctionArgumentList(array(
                        new ColumnReference(array('e')), new ColumnReference(array('f'))
                    ))
                ),
                new FunctionExpression(
                    'xmlconcat',
                    new FunctionArgumentList(array(
                        new ColumnReference(array('x')), new ColumnReference(array('m')), new ColumnReference(array('l'))
                    ))
                )
            )),
            $list
        );
    }

    public function testCollationFor()
    {
        $this->assertEquals(
            new FunctionExpression(
                new QualifiedName(array('pg_catalog', 'pg_collation_for')),
                new FunctionArgumentList(array(new ColumnReference(array('foo', 'bar'))))
            ),
            $this->parser->parseExpression('collation for (foo.bar)')
        );
    }

    public function testNamedAndVariadicParameters()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    blah.foo(variadic a), blah.bar(a, variadic b), blah.baz(a, b := c, binary := d)
QRY
        );

        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    new QualifiedName(array('blah', 'foo')),
                    new FunctionArgumentList(array(new ColumnReference(array('a')))),
                    false, true
                ),
                new FunctionExpression(
                    new QualifiedName(array('blah', 'bar')),
                    new FunctionArgumentList(
                        array(new ColumnReference(array('a')), new ColumnReference(array('b')))
                    ),
                    false, true
                ),
                new FunctionExpression(
                    new QualifiedName(array('blah', 'baz')),
                    new FunctionArgumentList(array(
                        new ColumnReference(array('a')),
                        '"b"'      => new ColumnReference(array('c')),
                        '"binary"' => new ColumnReference(array('d'))
                    ))
                )
            )),
            $list
        );
    }

    /**
     * @dataProvider getInvalidNamedAndVariadicParameters
     */
    public function testInvalidNamedAndVariadicParameters($functionCall, $message)
    {
        $this->setExpectedException(
            'sad_spirit\pg_builder\exceptions\SyntaxException',
            $message
        );
        $this->parser->parseExpression($functionCall);
    }

    public function getInvalidNamedAndVariadicParameters()
    {
        return array(
            array(
                'foo(variadic bar, baz)',
                "expecting special character with value ')'"
            ),
            array(
                'foo(a := b, c)',
                'Positional argument cannot follow named argument'
            ),
            array(
                'foo(a := b, a := c)',
                'used more than once'
            )
        );
    }

    /**
     * @dataProvider getInvalidFunctionNames
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     */
    public function testInvalidFunctionNames($functionCall)
    {
        $this->parser->parseExpression($functionCall);
    }

    public function getInvalidFunctionNames()
    {
        return array(
            array('out()'), // TYPE_COL_NAME_KEYWORD
            array('outer.foo()') // first part is TYPE_TYPE_FUNC_NAME_KEYWORD
        );
    }

    public function testAggregateFunctionCalls()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    agg1(all blah), agg2(distinct blahblah), agg3(distinct foo, bar order by foo desc, bar nulls last),
    count (*) filter (where foo > 100), percentile_disc(0.5) within group (order by foo)
QRY
        );

        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    new QualifiedName(array('agg1')),
                    new FunctionArgumentList(array(new ColumnReference(array('blah'))))
                ),
                new FunctionExpression(
                    new QualifiedName(array('agg2')),
                    new FunctionArgumentList(array(new ColumnReference(array('blahblah')))),
                    true
                ),
                new FunctionExpression(
                    new QualifiedName(array('agg3')),
                    new FunctionArgumentList(
                        array(
                            new ColumnReference(array(new Identifier('foo'))),
                            new ColumnReference(array(new Identifier('bar')))
                        )
                    ),
                    true, false, new OrderByList(array(
                        new OrderByElement(new ColumnReference(array(new Identifier('foo'))), 'desc'),
                        new OrderByElement(new ColumnReference(array('bar')), null, 'last')
                    ))
                ),
                new FunctionExpression(
                    new QualifiedName(array('count')),
                    new Star(),
                    false, false, null, false,
                    new OperatorExpression('>', new ColumnReference(array('foo')), new Constant(100))
                ),
                new FunctionExpression(
                    new QualifiedName(array('percentile_disc')),
                    new FunctionArgumentList(array(new Constant(0.5))),
                    false, false,
                    new OrderByList(array(new OrderByElement(new ColumnReference(array('foo'))))),
                    true
                )
            )),
            $list
        );
    }

    public function testWindowFunctionCalls()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo() over (), bar() over (blah), rank() over (partition by whatever),
    something() over (rows between 5 preceding and unbounded following exclude current row),
    count(bar) filter(where bar !@#&) over (partition by foo),
    foo() over (range between unbounded preceding and 3 following),
    bar() over (groups between current row and unbounded following exclude ties)
QRY
        );

        $this->assertEquals(
            new ExpressionList(array(
                new FunctionExpression(
                    new QualifiedName(array('foo')), null, false, false, null,
                    false, null, new WindowDefinition()
                ),
                new FunctionExpression(
                    new QualifiedName(array('bar')), null, false, false, null,
                    false, null, new WindowDefinition(new Identifier('blah'))
                ),
                new FunctionExpression(
                    new QualifiedName(array('rank')), null, false, false, null,
                    false, null, new WindowDefinition(null, new ExpressionList(array(new ColumnReference(array(new Identifier('whatever'))))))
                ),
                new FunctionExpression(
                    new QualifiedName(array('something')), null, false, false, null,
                    false, null, new WindowDefinition(
                        null, null, null,
                        new WindowFrameClause(
                            'rows',
                            new WindowFrameBound('preceding', new Constant(5)),
                            new WindowFrameBound('following'),
                            'current row'
                        )
                    )
                ),
                new FunctionExpression(
                    new QualifiedName(array('count')),
                    new FunctionArgumentList(array(new ColumnReference(array('bar')))),
                    false, false, null, false,
                    new OperatorExpression('!@#&', new ColumnReference(array('bar'))),
                    new WindowDefinition(null, new ExpressionList(array(new ColumnReference(array('foo')))))
                ),
                new FunctionExpression(
                    new QualifiedName(array('foo')), null, false, false, null, false, null,
                    new WindowDefinition(
                        null, null, null,
                        new WindowFrameClause(
                            'range',
                            new WindowFrameBound('preceding'),
                            new WindowFrameBound('following', new Constant(3))
                        )
                    )
                ),
                new FunctionExpression(
                    new QualifiedName(array('bar')), null, false, false, null, false, null,
                    new WindowDefinition(
                        null, null, null,
                        new WindowFrameClause(
                            'groups',
                            new WindowFrameBound('current row'),
                            new WindowFrameBound('following'),
                            'ties'
                        )
                    )
                )
            )),
            $list
        );
    }

    /**
     * @dataProvider getInvalidWindowSpecification
     */
    public function testInvalidWindowSpecifications($spec, $message)
    {
        $this->setExpectedException(
            'sad_spirit\pg_builder\exceptions\SyntaxException',
            $message
        );
        $this->parser->parseExpression($spec);
    }

    public function getInvalidWindowSpecification()
    {
        return array(
            array(
                'foo() over (rows unbounded following)',
                'Frame start cannot be UNBOUNDED FOLLOWING'
            ),
            array(
                'foo() over (rows 5 following)',
                'Frame starting from following row cannot end with current row'
            ),
            array(
                'foo() over (rows between unbounded following and unbounded following)',
                'Frame start cannot be UNBOUNDED FOLLOWING'
            ),
            array(
                'foo() over (rows between unbounded preceding and unbounded preceding)',
                'Frame end cannot be UNBOUNDED PRECEDING'
            ),
            array(
                'foo() over (rows between current row and 5 preceding)',
                'Frame starting from current row cannot have preceding rows'
            ),
            array(
                'foo() over (rows between 5 following and current row)',
                'Frame starting from following row cannot have preceding rows'
            ),
            array(
                'foo() over (rows between 5 following and 4 preceding)',
                'Frame starting from following row cannot have preceding rows'
            )
        );
    }

    /**
     * @dataProvider getInvalidWithinGroupUsage
     */
    public function testInvalidWithinGroupUsage($expression, $message)
    {
        $this->setExpectedException(
            'sad_spirit\pg_builder\exceptions\SyntaxException',
            $message
        );
        $this->parser->parseExpression($expression);
    }

    public function getInvalidWithinGroupUsage()
    {
        return array(
            array(
                'foo(a, b order by a) within group (order by b)',
                'Cannot use multiple ORDER BY clauses'
            ),
            array(
                'foo(distinct a) within group (order by a)',
                'Cannot use DISTINCT'
            ),
            array(
                'foo(variadic array[1,2,3]) within group (order by a)',
                'Cannot use VARIADIC'
            )
        );
    }
}