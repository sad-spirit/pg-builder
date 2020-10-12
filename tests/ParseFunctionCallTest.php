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

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\Lexer;
use sad_spirit\pg_builder\exceptions\SyntaxException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Constant,
    Identifier,
    OrderByElement,
    QualifiedName,
    Star,
    TargetElement,
    TypeName,
    WindowDefinition,
    WindowFrameBound,
    WindowFrameClause
};
use sad_spirit\pg_builder\nodes\expressions\{
    FunctionExpression,
    OperatorExpression,
    TypecastExpression
};
use sad_spirit\pg_builder\nodes\lists\{
    ExpressionList,
    FunctionArgumentList,
    OrderByList,
    TargetList,
    TypeModifierList
};
use sad_spirit\pg_builder\nodes\xml\{
    XmlElement,
    XmlForest,
    XmlParse,
    XmlPi,
    XmlRoot,
    XmlSerialize
};

/**
 * Tests parsing all possible function calls and function-like constructs
 */
class ParseFunctionCallTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testNoParenthesesFunctions()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    current_date, current_role, current_user, session_user, user, current_catalog, current_schema
QRY
        );
        $expected = [
            new TypecastExpression(
                new Constant('now'),
                new TypeName(new QualifiedName('pg_catalog', 'date'))
            )
        ];
        foreach (
            ['current_user', 'current_user', 'session_user', 'current_user',
                       'current_database', 'current_schema'] as $fn
        ) {
            $expected[] = new FunctionExpression(new QualifiedName('pg_catalog', $fn));
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
            new ExpressionList([
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(new QualifiedName('pg_catalog', 'timetz'))
                ),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(
                        new QualifiedName('pg_catalog', 'timestamptz'),
                        new TypeModifierList([new Constant(1)])
                    )
                ),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(
                        new QualifiedName('pg_catalog', 'time'),
                        new TypeModifierList([new Constant(2)])
                    )
                ),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(new QualifiedName('pg_catalog', 'timestamp'))
                )
            ]),
            $list
        );

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('expecting integer literal');
        $this->parser->parseExpressionList('current_time(foo)');
    }

    public function testExtract()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    extract(epoch from foo), extract(minute from bar), extract('whatever' from baz)
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'date_part'),
                    new FunctionArgumentList([new Constant('epoch'), new ColumnReference('foo')])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'date_part'),
                    new FunctionArgumentList([new Constant('minute'), new ColumnReference('bar')])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'date_part'),
                    new FunctionArgumentList([new Constant('whatever'), new ColumnReference('baz')])
                )
            ]),
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
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'overlay'),
                    new FunctionArgumentList([
                        new Constant('fooxxxbaz'), new Constant('bar'), new Constant(3), new Constant(3)
                    ])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'overlay'),
                    new FunctionArgumentList([
                        new Constant('adc'), new Constant('b'), new Constant(2)
                    ])
                )
            ]),
            $list
        );
    }

    public function testPosition()
    {
        $this->assertEquals(
            new FunctionExpression(
                new QualifiedName('pg_catalog', 'position'),
                new FunctionArgumentList([new Constant('foobar'), new Constant('a')])
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
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'substring'),
                    new FunctionArgumentList([new Constant('foobar'), new Constant(2), new Constant(3)])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'substring'),
                    new FunctionArgumentList([new Constant('foobar'), new Constant(2), new Constant(3)])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'substring'),
                    new FunctionArgumentList([new Constant('foobar'), new Constant(2), new Constant(3)])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'substring'),
                    new FunctionArgumentList([new Constant('foobar'), new Constant(1), new Constant(3)])
                )
            ]),
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
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'btrim'),
                    new FunctionArgumentList([new Constant(' foo ')])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'ltrim'),
                    new FunctionArgumentList([new Constant('_foo_'), new Constant('_')])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'rtrim'),
                    new FunctionArgumentList([new Constant('foo ')])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'rtrim'),
                    new FunctionArgumentList([new Constant('foo'), new Constant('o')])
                ),
            ]),
            $list
        );
    }

    public function testNullif()
    {
        $this->assertEquals(
            new FunctionExpression(
                'nullif',
                new FunctionArgumentList([new ColumnReference('a'), new Constant('b')])
            ),
            $this->parser->parseExpression("nullif(a, 'b') ")
        );

        $this->expectException(SyntaxException::class);
        $this->parser->parseExpression('nullif(a, b, c)');
    }

    public function testXmlElement()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    xmlelement(name foo, bar, 'content'), xmlelement(name blah, xmlattributes(baz, quux as xyzzy), 'content')
QRY
        );

        $this->assertEquals(
            new ExpressionList([
                new XmlElement(
                    new Identifier('foo'),
                    null,
                    new ExpressionList([new ColumnReference('bar'), new Constant('content')])
                ),
                new XmlElement(
                    new Identifier('blah'),
                    new TargetList([
                        new TargetElement(new ColumnReference('baz')),
                        new TargetElement(new ColumnReference('quux'), new Identifier('xyzzy'))
                    ]),
                    new ExpressionList([new Constant('content')])
                )
            ]),
            $list
        );
    }

    public function testXmlExists()
    {
        $this->assertEquals(
            new FunctionExpression(
                new QualifiedName('pg_catalog', 'xmlexists'),
                new FunctionArgumentList([
                    new Constant("//foo[text() = 'bar']"),
                    new Constant('<blah><foo>bar</foo></blah>')
                ])
            ),
            $this->parser->parseExpression(
                "xmlexists('//foo[text() = ''bar'']' passing by ref '<blah><foo>bar</foo></blah>')"
            )
        );
    }

    public function testXmlForest()
    {
        $this->assertEquals(
            new XmlForest([
                new TargetElement(new ColumnReference('foo')),
                new TargetElement(new Constant('bar'), new Identifier('baz'))
            ]),
            $this->parser->parseExpression("xmlforest(foo, 'bar' as baz)")
        );
    }

    public function testXmlParse()
    {
        $this->assertEquals(
            new XmlParse('document', new ColumnReference('xml', 'doc'), true),
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
            new XmlRoot(new ColumnReference('doc'), new Constant('1.2'), 'yes'),
            $this->parser->parseExpression("xmlroot(doc, version '1.2', standalone yes)")
        );
    }

    public function testXmlSerialize()
    {
        $this->assertEquals(
            new XmlSerialize(
                'document',
                new ColumnReference('foo'),
                new TypeName(new QualifiedName('pg_catalog', 'text'))
            ),
            $this->parser->parseExpression('xmlserialize(document foo as pg_catalog.text)')
        );
    }

    public function testNormalize()
    {
        $this::assertEquals(
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'normalize'),
                    new FunctionArgumentList([new ColumnReference('foo')])
                ),
                new FunctionExpression(
                    new QualifiedName('pg_catalog', 'normalize'),
                    new FunctionArgumentList([new ColumnReference('bar'), new Constant('nfd')])
                )
            ]),
            $this->parser->parseExpressionList('normalize(foo), normalize(bar, nFd)')
        );

        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage("Unexpected special character ','");
        $this->parser->parseExpression("normalize(baz, nfc, nfd)");
    }

    public function testExplicitlyKnownFunctionNames()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    coalesce(a, 'b'), greatest('c', d), least(e, f), xmlconcat(x, m, l)
QRY
        );

        $this->assertEquals(
            new ExpressionList([
                new FunctionExpression(
                    'coalesce',
                    new FunctionArgumentList([new ColumnReference('a'), new Constant('b')])
                ),
                new FunctionExpression(
                    'greatest',
                    new FunctionArgumentList([new Constant('c'), new ColumnReference('d')])
                ),
                new FunctionExpression(
                    'least',
                    new FunctionArgumentList([
                        new ColumnReference('e'), new ColumnReference('f')
                    ])
                ),
                new FunctionExpression(
                    'xmlconcat',
                    new FunctionArgumentList([
                        new ColumnReference('x'), new ColumnReference('m'), new ColumnReference('l')
                    ])
                )
            ]),
            $list
        );
    }

    public function testCollationFor()
    {
        $this->assertEquals(
            new FunctionExpression(
                new QualifiedName('pg_catalog', 'pg_collation_for'),
                new FunctionArgumentList([new ColumnReference('foo', 'bar')])
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
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('blah', 'foo'),
                    new FunctionArgumentList([new ColumnReference('a')]),
                    false,
                    true
                ),
                new FunctionExpression(
                    new QualifiedName('blah', 'bar'),
                    new FunctionArgumentList(
                        [new ColumnReference('a'), new ColumnReference('b')]
                    ),
                    false,
                    true
                ),
                new FunctionExpression(
                    new QualifiedName('blah', 'baz'),
                    new FunctionArgumentList([
                        new ColumnReference('a'),
                        'b'        => new ColumnReference('c'),
                        '"binary"' => new ColumnReference('d')
                    ])
                )
            ]),
            $list
        );
    }

    /**
     * @dataProvider getInvalidNamedAndVariadicParameters
     * @param string $functionCall
     * @param string $message
     */
    public function testInvalidNamedAndVariadicParameters(string $functionCall, string $message)
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($functionCall);
    }

    public function getInvalidNamedAndVariadicParameters()
    {
        return [
            [
                'foo(variadic bar, baz)',
                "expecting special character ')'"
            ],
            [
                'foo(a := b, c)',
                'Positional argument cannot follow named argument'
            ],
            [
                'foo(a := b, a := c)',
                'used more than once'
            ]
        ];
    }

    /**
     * @dataProvider getInvalidFunctionNames
     * @param string $functionCall
     */
    public function testInvalidFunctionNames(string $functionCall)
    {
        $this->expectException(SyntaxException::class);
        $this->parser->parseExpression($functionCall);
    }

    public function getInvalidFunctionNames()
    {
        return [
            ['out()'], // TYPE_COL_NAME_KEYWORD
            ['outer.foo()'] // first part is TYPE_TYPE_FUNC_NAME_KEYWORD
        ];
    }

    public function testAggregateFunctionCalls()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    agg1(all blah), agg2(distinct blahblah), agg3(distinct foo, bar order by foo desc, bar nulls last),
    count (*) filter (where foo > 100), percentile_disc(0.5) within group (order by foo)
QRY
        );

        $this->assertEquals(
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('agg1'),
                    new FunctionArgumentList([new ColumnReference('blah')])
                ),
                new FunctionExpression(
                    new QualifiedName('agg2'),
                    new FunctionArgumentList([new ColumnReference('blahblah')]),
                    true
                ),
                new FunctionExpression(
                    new QualifiedName('agg3'),
                    new FunctionArgumentList(
                        [
                            new ColumnReference(new Identifier('foo')),
                            new ColumnReference(new Identifier('bar'))
                        ]
                    ),
                    true,
                    false,
                    new OrderByList([
                        new OrderByElement(new ColumnReference(new Identifier('foo')), 'desc'),
                        new OrderByElement(new ColumnReference('bar'), null, 'last')
                    ])
                ),
                new FunctionExpression(
                    new QualifiedName('count'),
                    new Star(),
                    false,
                    false,
                    null,
                    false,
                    new OperatorExpression('>', new ColumnReference('foo'), new Constant(100))
                ),
                new FunctionExpression(
                    new QualifiedName('percentile_disc'),
                    new FunctionArgumentList([new Constant(0.5)]),
                    false,
                    false,
                    new OrderByList([new OrderByElement(new ColumnReference('foo'))]),
                    true
                )
            ]),
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
            new ExpressionList([
                new FunctionExpression(
                    new QualifiedName('foo'),
                    null,
                    false,
                    false,
                    null,
                    false,
                    null,
                    new WindowDefinition()
                ),
                new FunctionExpression(
                    new QualifiedName('bar'),
                    null,
                    false,
                    false,
                    null,
                    false,
                    null,
                    new WindowDefinition(new Identifier('blah'))
                ),
                new FunctionExpression(
                    new QualifiedName('rank'),
                    null,
                    false,
                    false,
                    null,
                    false,
                    null,
                    new WindowDefinition(null, new ExpressionList([new ColumnReference(new Identifier('whatever'))]))
                ),
                new FunctionExpression(
                    new QualifiedName('something'),
                    null,
                    false,
                    false,
                    null,
                    false,
                    null,
                    new WindowDefinition(
                        null,
                        null,
                        null,
                        new WindowFrameClause(
                            'rows',
                            new WindowFrameBound('preceding', new Constant(5)),
                            new WindowFrameBound('following'),
                            'current row'
                        )
                    )
                ),
                new FunctionExpression(
                    new QualifiedName('count'),
                    new FunctionArgumentList([new ColumnReference('bar')]),
                    false,
                    false,
                    null,
                    false,
                    new OperatorExpression('!@#&', new ColumnReference('bar')),
                    new WindowDefinition(null, new ExpressionList([new ColumnReference('foo')]))
                ),
                new FunctionExpression(
                    new QualifiedName('foo'),
                    null,
                    false,
                    false,
                    null,
                    false,
                    null,
                    new WindowDefinition(
                        null,
                        null,
                        null,
                        new WindowFrameClause(
                            'range',
                            new WindowFrameBound('preceding'),
                            new WindowFrameBound('following', new Constant(3))
                        )
                    )
                ),
                new FunctionExpression(
                    new QualifiedName('bar'),
                    null,
                    false,
                    false,
                    null,
                    false,
                    null,
                    new WindowDefinition(
                        null,
                        null,
                        null,
                        new WindowFrameClause(
                            'groups',
                            new WindowFrameBound('current row'),
                            new WindowFrameBound('following'),
                            'ties'
                        )
                    )
                )
            ]),
            $list
        );
    }

    /**
     * @dataProvider getInvalidWindowSpecification
     * @param string $spec
     * @param string $message
     */
    public function testInvalidWindowSpecifications(string $spec, string $message)
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($spec);
    }

    public function getInvalidWindowSpecification()
    {
        return [
            [
                'foo() over (rows unbounded following)',
                'Frame start cannot be UNBOUNDED FOLLOWING'
            ],
            [
                'foo() over (rows 5 following)',
                'Frame starting from following row cannot end with current row'
            ],
            [
                'foo() over (rows between unbounded following and unbounded following)',
                'Frame start cannot be UNBOUNDED FOLLOWING'
            ],
            [
                'foo() over (rows between unbounded preceding and unbounded preceding)',
                'Frame end cannot be UNBOUNDED PRECEDING'
            ],
            [
                'foo() over (rows between current row and 5 preceding)',
                'Frame starting from current row cannot have preceding rows'
            ],
            [
                'foo() over (rows between 5 following and current row)',
                'Frame starting from following row cannot have preceding rows'
            ],
            [
                'foo() over (rows between 5 following and 4 preceding)',
                'Frame starting from following row cannot have preceding rows'
            ]
        ];
    }

    /**
     * @dataProvider getInvalidWithinGroupUsage
     * @param string $expression
     * @param string $message
     */
    public function testInvalidWithinGroupUsage(string $expression, string $message)
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($expression);
    }

    public function getInvalidWithinGroupUsage()
    {
        return [
            [
                'foo(a, b order by a) within group (order by b)',
                'Cannot use multiple ORDER BY clauses'
            ],
            [
                'foo(distinct a) within group (order by a)',
                'Cannot use DISTINCT'
            ],
            [
                'foo(variadic array[1,2,3]) within group (order by a)',
                'Cannot use VARIADIC'
            ]
        ];
    }
}
