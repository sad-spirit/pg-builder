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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    Values
};
use sad_spirit\pg_builder\exceptions\SyntaxException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
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
    CollationForExpression,
    ExtractExpression,
    FunctionExpression,
    NormalizeExpression,
    NullIfExpression,
    OverlayExpression,
    PositionExpression,
    RowExpression,
    SQLValueFunction,
    NumericConstant,
    OperatorExpression,
    StringConstant,
    SubstringFromExpression,
    SubstringSimilarExpression,
    SystemFunctionCall,
    TrimExpression
};
use sad_spirit\pg_builder\nodes\json\{
    JsonArrayAgg,
    JsonArraySubselect,
    JsonArrayValueList,
    JsonFormat,
    JsonFormattedValue,
    JsonFormattedValueList,
    JsonKeyValue,
    JsonKeyValueList,
    JsonObject,
    JsonObjectAgg,
    JsonReturning
};
use sad_spirit\pg_builder\nodes\lists\{
    ExpressionList,
    FunctionArgumentList,
    OrderByList,
    RowList,
    TargetList
};
use sad_spirit\pg_builder\nodes\xml\{
    XmlElement,
    XmlExists,
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

    public function testNoParenthesesFunctions(): void
    {
        $list = $this->parser->parseExpressionList($input = <<<QRY
    current_date, current_role, current_user, session_user, user, current_catalog, current_schema, system_user
QRY
        );
        $expected = [];
        foreach (array_map('trim', explode(',', $input)) as $item) {
            $expected[] = new SQLValueFunction($item);
        }

        $this::assertEquals(new ExpressionList($expected), $list);
    }

    public function testOptionalParenthesesFunctions(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    current_time, current_timestamp(1), localtime(2), localtimestamp
QRY
        );

        $this::assertEquals(
            new ExpressionList([
                new SQLValueFunction(SQLValueFunction::CURRENT_TIME),
                new SQLValueFunction(SQLValueFunction::CURRENT_TIMESTAMP, new NumericConstant('1')),
                new SQLValueFunction(SQLValueFunction::LOCALTIME, new NumericConstant('2')),
                new SQLValueFunction(SQLValueFunction::LOCALTIMESTAMP)
            ]),
            $list
        );

        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage('expecting integer literal');
        $this->parser->parseExpressionList('current_time(foo)');
    }

    public function testExtract(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    extract(epoch from foo), extract(minute from bar), extract('whatever' from baz)
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new ExtractExpression('epoch', new ColumnReference('foo')),
                new ExtractExpression('minute', new ColumnReference('bar')),
                new ExtractExpression('whatever', new ColumnReference('baz'))
            ]),
            $list
        );
    }

    public function testOverlay(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    overlay('fooxxxbaz' placing 'bar' from 3 for 3),
    overlay('adc' placing 'b' from 2),
    overlay(),
    overlay(foo => bar),
    overlay('foo'),
    overlay('foo', 2, 3)
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new OverlayExpression(
                    new StringConstant('fooxxxbaz'),
                    new StringConstant('bar'),
                    new NumericConstant('3'),
                    new NumericConstant('3')
                ),
                new OverlayExpression(new StringConstant('adc'), new StringConstant('b'), new NumericConstant('2')),
                new FunctionExpression(new QualifiedName('overlay'), new FunctionArgumentList()),
                new FunctionExpression(
                    new QualifiedName('overlay'),
                    new FunctionArgumentList(['foo' => new ColumnReference('bar')])
                ),
                new FunctionExpression(
                    new QualifiedName('overlay'),
                    new FunctionArgumentList([new StringConstant('foo')])
                ),
                new FunctionExpression(
                    new QualifiedName('overlay'),
                    new FunctionArgumentList(
                        [new StringConstant('foo'), new NumericConstant('2'), new NumericConstant('3')]
                    )
                )
            ]),
            $list
        );
    }

    public function testPosition(): void
    {
        $this->assertEquals(
            new PositionExpression(new StringConstant('a'), new StringConstant('foobar')),
            $this->parser->parseExpression("position('a' in 'foobar')")
        );
    }

    public function testSubstring(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    substring(),
    substring(foo := bar),
    substring('foobar'),
    substring('foobar', 2, 3),
    substring('foobar' from 2 for 3), 
    substring('foobar' for 3 from 2), 
    substring('foobar' for 3), 
    substring('foobar' from 3), 
    substring('foobar' similar 'foo' escape '#')
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new FunctionExpression(new QualifiedName('substring'), new FunctionArgumentList()),
                new FunctionExpression(
                    new QualifiedName('substring'),
                    new FunctionArgumentList(['foo' => new ColumnReference('bar')])
                ),
                new FunctionExpression(
                    new QualifiedName('substring'),
                    new FunctionArgumentList([new StringConstant('foobar')])
                ),
                new FunctionExpression(
                    new QualifiedName('substring'),
                    new FunctionArgumentList([
                        new StringConstant('foobar'), new NumericConstant('2'), new NumericConstant('3')
                    ])
                ),
                new SubstringFromExpression(
                    new StringConstant('foobar'),
                    new NumericConstant('2'),
                    new NumericConstant('3')
                ),
                new SubstringFromExpression(
                    new StringConstant('foobar'),
                    new NumericConstant('2'),
                    new NumericConstant('3')
                ),
                new SubstringFromExpression(new StringConstant('foobar'), null, new NumericConstant('3')),
                new SubstringFromExpression(new StringConstant('foobar'), new NumericConstant('3'), null),
                new SubstringSimilarExpression(
                    new StringConstant('foobar'),
                    new StringConstant('foo'),
                    new StringConstant('#')
                )
            ]),
            $list
        );
    }

    public function testTrim(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    trim(from ' foo '), trim(leading '_' from '_foo_'), trim(trailing from 'foo '), trim(trailing from 'foo', 'o'),
    trim(from 'foo', 'f', 'o') -- this will ultimately result in error in Postgres, but should parse
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new TrimExpression(new ExpressionList([new StringConstant(' foo ')])),
                new TrimExpression(
                    new ExpressionList([new StringConstant('_foo_'), new StringConstant('_')]),
                    TrimExpression::LEADING
                ),
                new TrimExpression(
                    new ExpressionList([new StringConstant('foo ')]),
                    TrimExpression::TRAILING
                ),
                new TrimExpression(
                    new ExpressionList([new StringConstant('foo'), new StringConstant('o')]),
                    TrimExpression::TRAILING
                ),
                new TrimExpression(new ExpressionList([
                    new StringConstant('foo'),
                    new StringConstant('f'),
                    new StringConstant('o')
                ]))
            ]),
            $list
        );
    }

    public function testNullif(): void
    {
        $this->assertEquals(
            new NullIfExpression(new ColumnReference('a'), new StringConstant('b')),
            $this->parser->parseExpression("nullif(a, 'b') ")
        );

        $this->expectException(SyntaxException::class);
        $this->parser->parseExpression('nullif(a, b, c)');
    }

    public function testXmlElement(): void
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
                    new ExpressionList([new ColumnReference('bar'), new StringConstant('content')])
                ),
                new XmlElement(
                    new Identifier('blah'),
                    new TargetList([
                        new TargetElement(new ColumnReference('baz')),
                        new TargetElement(new ColumnReference('quux'), new Identifier('xyzzy'))
                    ]),
                    new ExpressionList([new StringConstant('content')])
                )
            ]),
            $list
        );
    }

    public function testXmlExists(): void
    {
        $this->assertEquals(
            new XmlExists(
                new StringConstant("//foo[text() = 'bar']"),
                new StringConstant('<blah><foo>bar</foo></blah>')
            ),
            $this->parser->parseExpression(
                "xmlexists('//foo[text() = ''bar'']' passing by ref '<blah><foo>bar</foo></blah>')"
            )
        );
    }

    public function testXmlForest(): void
    {
        $this->assertEquals(
            new XmlForest([
                new TargetElement(new ColumnReference('foo')),
                new TargetElement(new StringConstant('bar'), new Identifier('baz'))
            ]),
            $this->parser->parseExpression("xmlforest(foo, 'bar' as baz)")
        );
    }

    public function testXmlParse(): void
    {
        $this->assertEquals(
            new XmlParse('document', new ColumnReference('xml', 'doc'), true),
            $this->parser->parseExpression("xmlparse(document xml.doc preserve whitespace)")
        );
    }

    public function testXmlPi(): void
    {
        $this->assertEquals(
            new XmlPi(new Identifier('php'), new StringConstant("echo 'Hello world!';")),
            $this->parser->parseExpression("xmlpi(name php, 'echo ''Hello world!'';')")
        );
    }

    public function testXmlRoot(): void
    {
        $this->assertEquals(
            new XmlRoot(new ColumnReference('doc'), new StringConstant('1.2'), 'yes'),
            $this->parser->parseExpression("xmlroot(doc, version '1.2', standalone yes)")
        );
    }

    public function testXmlSerialize(): void
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

    public function testNormalize(): void
    {
        $this::assertEquals(
            new ExpressionList([
                new NormalizeExpression(new ColumnReference('foo')),
                new NormalizeExpression(new ColumnReference('bar'), NormalizeExpression::NFD)
            ]),
            $this->parser->parseExpressionList('normalize(foo), normalize(bar, nFd)')
        );

        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage("Unexpected special character ','");
        $this->parser->parseExpression("normalize(baz, nfc, nfd)");
    }

    public function testFunctionsWithKeywordNames(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    coalesce(a, 'b'), greatest('c', d), least(e, f), xmlconcat(x, m, l)
QRY
        );

        $this->assertEquals(
            new ExpressionList([
                new SystemFunctionCall(
                    'coalesce',
                    new ExpressionList([new ColumnReference('a'), new StringConstant('b')])
                ),
                new SystemFunctionCall(
                    'greatest',
                    new ExpressionList([new StringConstant('c'), new ColumnReference('d')])
                ),
                new SystemFunctionCall(
                    'least',
                    new ExpressionList([new ColumnReference('e'), new ColumnReference('f')])
                ),
                new SystemFunctionCall(
                    'xmlconcat',
                    new ExpressionList([
                        new ColumnReference('x'), new ColumnReference('m'), new ColumnReference('l')
                    ])
                )
            ]),
            $list
        );
    }

    public function testCollationFor(): void
    {
        $this->assertEquals(
            new CollationForExpression(new ColumnReference('foo', 'bar')),
            $this->parser->parseExpression('collation for (foo.bar)')
        );
    }

    public function testNamedAndVariadicParameters(): void
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
    public function testInvalidNamedAndVariadicParameters(string $functionCall, string $message): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($functionCall);
    }

    public function getInvalidNamedAndVariadicParameters(): array
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
    public function testInvalidFunctionNames(string $functionCall): void
    {
        $this->expectException(SyntaxException::class);
        $this->parser->parseExpression($functionCall);
    }

    public function getInvalidFunctionNames(): array
    {
        return [
            ['out()'], // TYPE_COL_NAME_KEYWORD
            ['outer.foo()'] // first part is TYPE_TYPE_FUNC_NAME_KEYWORD
        ];
    }

    public function testAggregateFunctionCalls(): void
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
                    new OperatorExpression('>', new ColumnReference('foo'), new NumericConstant('100'))
                ),
                new FunctionExpression(
                    new QualifiedName('percentile_disc'),
                    new FunctionArgumentList([new NumericConstant('0.5')]),
                    false,
                    false,
                    new OrderByList([new OrderByElement(new ColumnReference('foo'))]),
                    true
                )
            ]),
            $list
        );
    }

    public function testJsonAggregates(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    json_objectagg(k value v), json_arrayagg(v format json encoding utf32 returning blah),
    json_objectagg(k: v null on null) filter (where v > 0) over (win95)
QRY
        );
        $this::assertEquals(
            new ExpressionList([
                new JsonObjectAgg(new JsonKeyValue(
                    new ColumnReference('k'),
                    new JsonFormattedValue(new ColumnReference('v'))
                )),
                new JsonArrayAgg(
                    new JsonFormattedValue(new ColumnReference('v'), new JsonFormat('json', 'utf32')),
                    null,
                    null,
                    new JsonReturning(new TypeName(new QualifiedName('blah')))
                ),
                new JsonObjectAgg(
                    new JsonKeyValue(new ColumnReference('k'), new JsonFormattedValue(new ColumnReference('v'))),
                    false,
                    null,
                    null,
                    new OperatorExpression('>', new ColumnReference('v'), new NumericConstant('0')),
                    new WindowDefinition(new Identifier('win95'))
                )
            ]),
            $list
        );
    }

    public function testJsonObjectConstructor(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    json_object(),
    json_object(returning jsonb),
    json_object('{foo,bar}'),
    json_object('{foo,bar}', '{baz,quux}'),
    json_object(k: v null on null),
    json_object(k: v, kk value vv without unique keys)
QRY
        );

        $this::assertEquals(
            new ExpressionList([
                new JsonObject(),
                new JsonObject(null, null, null, new JsonReturning(new TypeName(new QualifiedName('jsonb')))),
                new FunctionExpression(
                    new QualifiedName('json_object'),
                    new FunctionArgumentList([new StringConstant('{foo,bar}')])
                ),
                new FunctionExpression(
                    new QualifiedName('json_object'),
                    new FunctionArgumentList([new StringConstant('{foo,bar}'), new StringConstant('{baz,quux}')])
                ),
                new JsonObject(new JsonKeyValueList([
                    new JsonKeyValue(new ColumnReference('k'), new JsonFormattedValue(new ColumnReference('v')))
                ]), false),
                new JsonObject(new JsonKeyValueList([
                    new JsonKeyValue(new ColumnReference('k'), new JsonFormattedValue(new ColumnReference('v'))),
                    new JsonKeyValue(new ColumnReference('kk'), new JsonFormattedValue(new ColumnReference('vv')))
                ]), null, false)
            ]),
            $list
        );
    }

    public function testJsonArrayConstructor(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    json_array(),
    json_array(returning jsonb),
    json_array(one format json, two null on null),
    json_array((foo + bar)),
    json_array((values (2), (1), (3)) order by 1 returning bytea)
QRY
        );

        $values = new Values(new RowList([
            new RowExpression([new NumericConstant('2')]),
            new RowExpression([new NumericConstant('1')]),
            new RowExpression([new NumericConstant('3')])
        ]));
        $values->order[] = new OrderByElement(new NumericConstant('1'));

        $this::assertEquals(
            new ExpressionList([
                new JsonArrayValueList(),
                new JsonArrayValueList(null, null, new JsonReturning(new TypeName(new QualifiedName('jsonb')))),
                new JsonArrayValueList(new JsonFormattedValueList([
                    new JsonFormattedValue(new ColumnReference('one'), new JsonFormat()),
                    new JsonFormattedValue(new ColumnReference('two'))
                ]), false),
                new JsonArrayValueList(new JsonFormattedValueList([new JsonFormattedValue(
                    new OperatorExpression('+', new ColumnReference('foo'), new ColumnReference('bar'))
                )])),
                new JsonArraySubselect($values, null, new JsonReturning(new TypeName(new QualifiedName('bytea'))))
            ]),
            $list
        );
    }

    public function testWindowFunctionCalls(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo() over (), bar() over (blah), rank() over (partition by whatever),
    something() over (rows between 5 preceding and unbounded following exclude current row),
    count(bar) filter(where !@#& bar) over (partition by foo),
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
                            new WindowFrameBound('preceding', new NumericConstant('5')),
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
                    new OperatorExpression('!@#&', null, new ColumnReference('bar')),
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
                            new WindowFrameBound('following', new NumericConstant('3'))
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
    public function testInvalidWindowSpecifications(string $spec, string $message): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($spec);
    }

    public function getInvalidWindowSpecification(): array
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
    public function testInvalidWithinGroupUsage(string $expression, string $message): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($expression);
    }

    public function getInvalidWithinGroupUsage(): array
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
