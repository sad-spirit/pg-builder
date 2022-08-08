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
    Parser,
    Lexer,
    Select
};
use sad_spirit\pg_builder\exceptions\SyntaxException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    FunctionCall,
    Identifier,
    QualifiedName,
    TargetElement,
    TypeName
};
use sad_spirit\pg_builder\nodes\expressions\{
    KeywordConstant,
    NumericConstant,
    OperatorExpression,
    StringConstant,
    SubselectExpression,
    TypecastExpression
};
use sad_spirit\pg_builder\nodes\json\{
    JsonArgument,
    JsonArgumentList,
    JsonFormat,
    JsonFormattedValue,
    JsonKeywords
};
use sad_spirit\pg_builder\nodes\range\{
    ColumnDefinition,
    FunctionCall as RangeFunctionCall,
    JoinExpression,
    JsonTable,
    RelationReference,
    RowsFrom,
    RowsFromElement,
    Subselect,
    TableSample,
    UsingClause,
    XmlTable
};
use sad_spirit\pg_builder\nodes\range\json\{
    JsonColumnDefinitionList,
    JsonExistsColumnDefinition,
    JsonFormattedColumnDefinition,
    JsonNestedColumns,
    JsonOrdinalityColumnDefinition,
    JsonRegularColumnDefinition,
    JsonTableDefaultPlan,
    JsonTableParentChildPlan,
    JsonTablePlan,
    JsonTableSimplePlan
};
use sad_spirit\pg_builder\nodes\lists\{
    ExpressionList,
    FromList,
    FunctionArgumentList,
    IdentifierList,
    ColumnDefinitionList,
    RowsFromList,
    TargetList,
    TypeModifierList
};
use sad_spirit\pg_builder\nodes\xml\{
    XmlColumnList,
    XmlNamespace,
    XmlNamespaceList,
    XmlOrdinalityColumnDefinition,
    XmlTypedColumnDefinition
};

/**
 * Tests parsing all possible expressions appearing in FROM clause
 */
class ParseFromClauseTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testBasicItems(): void
    {
        $list = $this->parser->parseFromList(<<<QRY
    foo.bar, baz(1, 'string'), (select 'quux') as quux
QRY
        );
        $select = new Select(new TargetList([new TargetElement(new StringConstant('quux'))]));
        $subselect = new Subselect($select);
        $subselect->setAlias(new Identifier('quux'));

        $this->assertEquals(
            new FromList([
                new RelationReference(new QualifiedName('foo', 'bar')),
                new RangeFunctionCall(new FunctionCall(
                    new QualifiedName('baz'),
                    new FunctionArgumentList([new NumericConstant('1'), new StringConstant('string')])
                )),
                $subselect
            ]),
            $list
        );
    }

    public function testAliasedTables(): void
    {
        $list = $this->parser->parseFromList(<<<QRY
    foo as fooalias, only barschema.bar baralias, baz * as bazalias (one, two),
    quuxschema.quux * quuxalias (three, four)
QRY
        );
        $foo = new RelationReference(new QualifiedName('foo'));
        $foo->setAlias(new Identifier('fooalias'));

        $bar = new RelationReference(new QualifiedName('barschema', 'bar'), false);
        $bar->setAlias(new Identifier('baralias'));

        $baz = new RelationReference(new QualifiedName('baz'), true);
        $baz->setAlias(
            new Identifier('bazalias'),
            new IdentifierList(['one', 'two'])
        );

        $quux = new RelationReference(new QualifiedName('quuxschema', 'quux'), true);
        $quux->setAlias(
            new Identifier('quuxalias'),
            new IdentifierList(['three', 'four'])
        );

        $this->assertEquals(new FromList([$foo, $bar, $baz, $quux]), $list);
    }

    public function testAliasedFunctions(): void
    {
        // this also checks that keywords are allowed for ColId's
        $list = $this->parser->parseFromList(<<<QRY
    blah.blah(1, 2, 3) "select" (abort integer, alter text collate foo, begin double precision),
    blahblah(null) as (start character varying, temporary bit)
QRY
        );
        $blah = new RangeFunctionCall(new FunctionCall(
            new QualifiedName('blah', 'blah'),
            new FunctionArgumentList([new NumericConstant('1'), new NumericConstant('2'), new NumericConstant('3')])
        ));
        $blah->setAlias(new Identifier('select'), new ColumnDefinitionList([
            new ColumnDefinition(
                new Identifier('abort'),
                new TypeName(new QualifiedName('pg_catalog', 'int4'))
            ),
            new ColumnDefinition(
                new Identifier('alter'),
                new TypeName(new QualifiedName('text')),
                new QualifiedName('foo')
            ),
            new ColumnDefinition(
                new Identifier('begin'),
                new TypeName(new QualifiedName('pg_catalog', 'float8'))
            )
        ]));
        $blahblah = new RangeFunctionCall(new FunctionCall(
            new QualifiedName('blahblah'),
            new FunctionArgumentList([new KeywordConstant(KeywordConstant::NULL)])
        ));
        $blahblah->setAlias(null, new ColumnDefinitionList([
            new ColumnDefinition(
                new Identifier('start'),
                new TypeName(new QualifiedName('pg_catalog', 'varchar'))
            ),
            new ColumnDefinition(
                new Identifier('temporary'),
                new TypeName(
                    new QualifiedName('pg_catalog', 'bit'),
                    new TypeModifierList([new NumericConstant('1')])
                )
            )
        ]));

        $this->assertEquals(new FromList([$blah, $blahblah]), $list);
    }

    public function testJoins(): void
    {
        $list = $this->parser->parseFromList(<<<QRY
    a as aa natural join b bb left join (c right join d on (true = false)) as joinalias using (blah) as bleh,
    f full outer join g(1) on false <> true cross join lateral (select 'blah') as h
QRY
        );

        $a = new RelationReference(new QualifiedName('a'));
        $a->setAlias(new Identifier('aa'));

        $b = new RelationReference(new QualifiedName('b'));
        $b->setAlias(new Identifier('bb'));

        $ab = new JoinExpression($a, $b, 'inner');
        $ab->setNatural(true);

        $cd = new JoinExpression(
            new RelationReference(new QualifiedName('c')),
            new RelationReference(new QualifiedName('d')),
            'right'
        );
        $cd->setOn(new OperatorExpression(
            '=',
            new KeywordConstant(KeywordConstant::TRUE),
            new KeywordConstant(KeywordConstant::FALSE)
        ));
        $cd->setAlias(new Identifier('joinalias'));

        $abcd = new JoinExpression($ab, $cd, 'left');
        $abcd->setUsing(new UsingClause(['blah'], new Identifier('bleh')));

        $fg = new JoinExpression(
            new RelationReference(new QualifiedName('f')),
            new RangeFunctionCall(new FunctionCall(
                new QualifiedName('g'),
                new FunctionArgumentList([new NumericConstant('1')])
            )),
            'full'
        );
        $fg->setOn(new OperatorExpression(
            '<>',
            new KeywordConstant(KeywordConstant::FALSE),
            new KeywordConstant(KeywordConstant::TRUE)
        ));

        $select = new Select(new TargetList([new TargetElement(new StringConstant('blah'))]));
        $subselect = new Subselect($select);
        $subselect->setAlias(new Identifier('h'));
        $subselect->setLateral(true);

        $this->assertEquals(new FromList([$abcd, new JoinExpression($fg, $subselect, 'cross')]), $list);
    }

    public function testNoMoreThanTwoDots(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Too many dots');
        $this->parser->parseFromList('foo.bar.baz.quux');
    }

    public function testSubselectsRequireAnAlias(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('should have an alias');
        $this->parser->parseFromList("(select 'foo')");
    }

    public function testWithOrdinality(): void
    {
        $list = $this->parser->parseFromList(
            'foo(1) with ordinality, bar(2) with ordinality as blah'
        );
        $foo = new RangeFunctionCall(new FunctionCall(
            new QualifiedName('foo'),
            new FunctionArgumentList([new NumericConstant('1')])
        ));
        $foo->withOrdinality = true;
        $bar = new RangeFunctionCall(new FunctionCall(
            new QualifiedName('bar'),
            new FunctionArgumentList([new NumericConstant('2')])
        ));
        $bar->setWithOrdinality(true);
        $bar->setAlias(new Identifier('blah'));

        $this->assertEquals(new FromList([$foo, $bar]), $list);
    }

    public function testRowsFrom(): void
    {
        $list = $this->parser->parseFromList(<<<QRY
    rows from (foo(1) as (fooid integer, fooname text), foo(2)),
    rows from (generate_series(1, 5), generate_series(1,10)) with ordinality
QRY
        );
        $rowsOne = new RowsFrom(new RowsFromList([
            new RowsFromElement(
                new FunctionCall(
                    new QualifiedName('foo'),
                    new FunctionArgumentList([new NumericConstant('1')])
                ),
                new ColumnDefinitionList([
                    new ColumnDefinition(
                        new Identifier('fooid'),
                        new TypeName(new QualifiedName('pg_catalog', 'int4'))
                    ),
                    new ColumnDefinition(
                        new Identifier('fooname'),
                        new TypeName(new QualifiedName('text'))
                    ),
                ])
            ),
            new RowsFromElement(new FunctionCall(
                new QualifiedName('foo'),
                new FunctionArgumentList([new NumericConstant('2')])
            ))
        ]));
        $rowsTwo = new RowsFrom(new RowsFromList([
            new RowsFromElement(new FunctionCall(
                new QualifiedName('generate_series'),
                new FunctionArgumentList([new NumericConstant('1'), new NumericConstant('5')])
            )),
            new RowsFromElement(new FunctionCall(
                new QualifiedName('generate_series'),
                new FunctionArgumentList([new NumericConstant('1'), new NumericConstant('10')])
            ))
        ]));
        $rowsTwo->setWithOrdinality(true);

        $this->assertEquals(new FromList([$rowsOne, $rowsTwo]), $list);
    }

    public function testTableSample(): void
    {
        $list = $this->parser->parseFromList(<<<QRY
foo tablesample system (bar.baz * 100),
quux natural join xyzzy as a (b,c) tablesample bernoulli (50) repeatable (seed)
QRY
        );

        $sample1 = new TableSample(
            new RelationReference(new QualifiedName('foo')),
            new QualifiedName('system'),
            new ExpressionList([
                new OperatorExpression(
                    '*',
                    new ColumnReference('bar', 'baz'),
                    new NumericConstant('100')
                )
            ])
        );

        $sample2 = new JoinExpression(
            new RelationReference(new QualifiedName('quux')),
            new TableSample(
                new RelationReference(new QualifiedName('xyzzy')),
                new QualifiedName('bernoulli'),
                new ExpressionList([
                    new NumericConstant('50')
                ]),
                new ColumnReference('seed')
            )
        );
        $sample2->setNatural(true);
        $sample2->right->setAlias(
            new Identifier('a'),
            new IdentifierList(['b', 'c'])
        );

        $this->assertEquals(new FromList([$sample1, $sample2]), $list);
    }

    public function testXmlTable(): void
    {
        // from docs
        $list = $this->parser->parseFromList(<<<QRY
XMLTABLE(
    '//ROWS/ROW' PASSING data by value
    COLUMNS id int PATH '@id',
            ordinality FOR ORDINALITY,
            "COUNTRY_NAME" text,
            country_id text PATH 'COUNTRY_ID',
            size_sq_km float PATH 'SIZE[@unit = "sq_km"]',
            size_other text PATH 'concat(SIZE[@unit!="sq_km"], " ", SIZE[@unit!="sq_km"]/@unit)',
            premier_name text PATH 'PREMIER_NAME' DEFAULT 'not specified'
),
LATERAL XMLTABLE(
    XMLNAMESPACES(
        'http://example.com/myns' AS x,
        'http://example.com/b' AS "B"
    ),
    '/x:example/x:item' PASSING by value (SELECT data FROM xmldata)
    COLUMNS foo int PATH '@foo',
            bar int PATH '@B:bar'
) AS baz                        
QRY
        );

        $table1 = new XmlTable(
            new StringConstant('//ROWS/ROW'),
            new ColumnReference('data'),
            new XmlColumnList([
                new XmlTypedColumnDefinition(
                    new Identifier('id'),
                    new TypeName(new QualifiedName('pg_catalog', 'int4')),
                    new StringConstant('@id')
                ),
                new XmlOrdinalityColumnDefinition(new Identifier('ordinality')),
                new XmlTypedColumnDefinition(
                    new Identifier('COUNTRY_NAME'),
                    new TypeName(new QualifiedName('text'))
                ),
                new XmlTypedColumnDefinition(
                    new Identifier('country_id'),
                    new TypeName(new QualifiedName('text')),
                    new StringConstant('COUNTRY_ID')
                ),
                new XmlTypedColumnDefinition(
                    new Identifier('size_sq_km'),
                    new TypeName(new QualifiedName('pg_catalog', 'float8')),
                    new StringConstant('SIZE[@unit = "sq_km"]')
                ),
                new XmlTypedColumnDefinition(
                    new Identifier('size_other'),
                    new TypeName(new QualifiedName('text')),
                    new StringConstant('concat(SIZE[@unit!="sq_km"], " ", SIZE[@unit!="sq_km"]/@unit)')
                ),
                new XmlTypedColumnDefinition(
                    new Identifier('premier_name'),
                    new TypeName(new QualifiedName('text')),
                    new StringConstant('PREMIER_NAME'),
                    null,
                    new StringConstant('not specified')
                )
            ])
        );

        $subselect = new Select(new TargetList([new TargetElement(new ColumnReference('data'))]));
        $subselect->from[] = new RelationReference(new QualifiedName('xmldata'));
        $table2 = new XmlTable(
            new StringConstant('/x:example/x:item'),
            new SubselectExpression($subselect),
            new XmlColumnList([
                new XmlTypedColumnDefinition(
                    new Identifier('foo'),
                    new TypeName(new QualifiedName('pg_catalog', 'int4')),
                    new StringConstant('@foo')
                ),
                new XmlTypedColumnDefinition(
                    new Identifier('bar'),
                    new TypeName(new QualifiedName('pg_catalog', 'int4')),
                    new StringConstant('@B:bar')
                )
            ]),
            new XmlNamespaceList([
                new XmlNamespace(new StringConstant('http://example.com/myns'), new Identifier('x')),
                new XmlNamespace(new StringConstant('http://example.com/b'), new Identifier('B'))
            ])
        );

        $table2->setLateral(true);
        $table2->setAlias(new Identifier('baz'));

        $this->assertEquals(new FromList([$table1, $table2]), $list);
    }

    public function testJsonTable(): void
    {
        $list = $this->parser->parseFromList(<<<QRY
json_table(
    '{"foo":"bar"}', '$'
    columns (id for ordinality, foo text)
    plan default(inner, cross)
    empty on error
),
lateral json_table(
    jsonb 'null', 'lax $[*]' passing 1 + 2 as a, json '"foo"' as "b c"
    columns (
        id for ordinality,
        "text" text path '$' with wrapper,
        jsf jsonb format json encoding utf8 path '$' omit quotes,
        "exists" int exists path '$.aaa' true on error,
        nested path '$[1]' as p1 columns (
            a1 int,
            nested path '$[*]' as "p1 1" columns (
                a11 text
            ),
            b1 text
        )
    )
    plan (a outer (p1 outer "p1 1"))
) as jst
QRY
        );

        $table1 = new JsonTable(
            new JsonFormattedValue(new StringConstant('{"foo":"bar"}')),
            new StringConstant('$'),
            null,
            null,
            new JsonColumnDefinitionList([
                new JsonOrdinalityColumnDefinition(new Identifier('id')),
                new JsonRegularColumnDefinition(new Identifier('foo'), new TypeName(new QualifiedName('text')))
            ]),
            new JsonTableDefaultPlan(JsonTablePlan::INNER, JsonTablePlan::CROSS),
            JsonKeywords::BEHAVIOUR_EMPTY
        );

        $table2 = new JsonTable(
            new JsonFormattedValue(
                new TypecastExpression(new StringConstant('null'), new TypeName(new QualifiedName('jsonb')))
            ),
            new StringConstant('lax $[*]'),
            null,
            new JsonArgumentList([
                new JsonArgument(
                    new JsonFormattedValue(
                        new OperatorExpression('+', new NumericConstant('1'), new NumericConstant('2'))
                    ),
                    new Identifier('a')
                ),
                new JsonArgument(
                    new JsonFormattedValue(
                        new TypecastExpression(
                            new StringConstant('"foo"'),
                            new TypeName(new QualifiedName('pg_catalog', 'json'))
                        )
                    ),
                    new Identifier('b c')
                )
            ]),
            new JsonColumnDefinitionList([
                new JsonOrdinalityColumnDefinition(new Identifier('id')),
                new JsonRegularColumnDefinition(
                    new Identifier('text'),
                    new TypeName(new QualifiedName('text')),
                    new StringConstant('$'),
                    JsonKeywords::WRAPPER_UNCONDITIONAL
                ),
                new JsonFormattedColumnDefinition(
                    new Identifier('jsf'),
                    new TypeName(new QualifiedName('jsonb')),
                    new JsonFormat(JsonFormat::FORMAT_JSON, JsonFormat::ENCODING_UTF8),
                    new StringConstant('$'),
                    null,
                    false
                ),
                new JsonExistsColumnDefinition(
                    new Identifier('exists'),
                    new TypeName(new QualifiedName('pg_catalog', 'int4')),
                    new StringConstant('$.aaa'),
                    JsonKeywords::BEHAVIOUR_TRUE
                ),
                new JsonNestedColumns(
                    new StringConstant('$[1]'),
                    new Identifier('p1'),
                    new JsonColumnDefinitionList([
                        new JsonRegularColumnDefinition(
                            new Identifier('a1'),
                            new TypeName(new QualifiedName('pg_catalog', 'int4'))
                        ),
                        new JsonNestedColumns(
                            new StringConstant('$[*]'),
                            new Identifier('p1 1'),
                            new JsonColumnDefinitionList([
                                new JsonRegularColumnDefinition(
                                    new Identifier('a11'),
                                    new TypeName(new QualifiedName('text'))
                                )
                            ])
                        ),
                        new JsonRegularColumnDefinition(
                            new Identifier('b1'),
                            new TypeName(new QualifiedName('text'))
                        )
                    ])
                )
            ]),
            new JsonTableParentChildPlan(
                new JsonTableSimplePlan(new Identifier('a')),
                new JsonTableParentChildPlan(
                    new JsonTableSimplePlan(new Identifier('p1')),
                    new JsonTableSimplePlan(new Identifier('p1 1')),
                    JsonTablePlan::OUTER
                ),
                JsonTablePlan::OUTER
            )
        );

        $table2->setLateral(true);
        $table2->setAlias(new Identifier('jst'));

        $this::assertEquals(new FromList([$table1, $table2]), $list);
    }

    /**
     * @dataProvider getInvalidJsonTables
     * @param string $table
     * @param string $message
     */
    public function testInvalidJsonTable(string $table, string $message): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage($message);
        $this->parser->parseFromElement($table);
    }

    public function getInvalidJsonTables(): array
    {
        return [
            [
                "json_table('null', '$' columns (foo bar) plan default()",
                "Unexpected special character ')'"
            ],
            [
                "json_table('null', '$' columns (foo bar) plan default(inner, outer)",
                "Unexpected keyword 'outer'"
            ],
            [
                "json_table('null', '$' columns (foo bar) plan (((p)))",
                "Unexpected special character ')'"
            ],
            [
                "json_table('null', '$' columns (foo bar) plan ((a inner b) inner c)",
                "Unexpected keyword 'inner'"
            ]
        ];
    }
}
