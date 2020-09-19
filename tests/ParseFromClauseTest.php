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

use sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\FunctionCall,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\TypeName,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\expressions\SubselectExpression,
    sad_spirit\pg_builder\nodes\range\JoinExpression,
    sad_spirit\pg_builder\nodes\range\RelationReference,
    sad_spirit\pg_builder\nodes\range\FunctionCall as RangeFunctionCall,
    sad_spirit\pg_builder\nodes\range\RowsFrom,
    sad_spirit\pg_builder\nodes\range\RowsFromElement,
    sad_spirit\pg_builder\nodes\range\Subselect,
    sad_spirit\pg_builder\nodes\range\ColumnDefinition,
    sad_spirit\pg_builder\nodes\range\TableSample,
    sad_spirit\pg_builder\nodes\range\XmlTable,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\lists\FromList,
    sad_spirit\pg_builder\nodes\lists\FunctionArgumentList,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\nodes\lists\ColumnDefinitionList,
    sad_spirit\pg_builder\nodes\lists\RowsFromList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\TypeModifierList,
    sad_spirit\pg_builder\nodes\xml\XmlColumnDefinition,
    sad_spirit\pg_builder\nodes\xml\XmlColumnList,
    sad_spirit\pg_builder\nodes\xml\XmlNamespace,
    sad_spirit\pg_builder\nodes\xml\XmlNamespaceList;

/**
 * Tests parsing all possible expressions appearing in FROM clause
 */
class ParseFromClauseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testBasicItems()
    {
        $list = $this->parser->parseFromList(<<<QRY
    foo.bar, baz(1, 'string'), (select 'quux') as quux
QRY
        );
        $select = new Select(new TargetList(array(new TargetElement(new Constant('quux')))));
        $subselect = new Subselect($select);
        $subselect->setAlias(new Identifier('quux'));

        $this->assertEquals(
            new FromList(array(
                new RelationReference(new QualifiedName(array('foo', 'bar'))),
                new RangeFunctionCall(new FunctionCall(
                    new QualifiedName(array('baz')),
                    new FunctionArgumentList(array(new Constant(1), new Constant('string')))
                )),
                $subselect
            )),
            $list
        );
    }

    public function testAliasedTables()
    {
        $list = $this->parser->parseFromList(<<<QRY
    foo as fooalias, only barschema.bar baralias, baz * as bazalias (one, two),
    quuxschema.quux * quuxalias (three, four)
QRY
        );
        $foo = new RelationReference(new QualifiedName(array('foo')));
        $foo->setAlias(new Identifier('fooalias'));
        $bar = new RelationReference(new QualifiedName(array('barschema', 'bar')), false);
        $bar->setAlias(new Identifier('baralias'));
        $baz = new RelationReference(new QualifiedName(array('baz')), true);
        $baz->setAlias(
            new Identifier('bazalias'),
            new IdentifierList(array('one', 'two'))
        );
        $quux = new RelationReference(new QualifiedName(array('quuxschema', 'quux')), true);
        $quux->setAlias(
            new Identifier('quuxalias'),
            new IdentifierList(array('three', 'four'))
        );

        $this->assertEquals(new FromList(array($foo, $bar, $baz, $quux)), $list);
    }

    public function testAliasedFunctions()
    {
        // this also checks that keywords are allowed for ColId's
        $list = $this->parser->parseFromList(<<<QRY
    blah.blah(1, 2, 3) "select" (abort integer, alter text collate foo, begin double precision),
    blahblah(null) as (start character varying, temporary bit)
QRY
        );
        $blah = new RangeFunctionCall(new FunctionCall(
            new QualifiedName(array('blah', 'blah')),
            new FunctionArgumentList(array(new Constant(1), new Constant(2), new Constant(3)))
        ));
        $blah->setAlias(new Identifier('select'), new ColumnDefinitionList(array(
            new ColumnDefinition(
                new Identifier('abort'), new TypeName(new QualifiedName(array('pg_catalog', 'int4')))
            ),
            new ColumnDefinition(
                new Identifier('alter'), new TypeName(new QualifiedName(array('text'))),
                new QualifiedName(array('foo'))
            ),
            new ColumnDefinition(
                new Identifier('begin'), new TypeName(new QualifiedName(array('pg_catalog', 'float8')))
            )
        )));
        $blahblah = new RangeFunctionCall(new FunctionCall(
            new QualifiedName(array('blahblah')), new FunctionArgumentList(array(new Constant(null)))
        ));
        $blahblah->setAlias(null, new ColumnDefinitionList(array(
            new ColumnDefinition(
                new Identifier('start'), new TypeName(new QualifiedName(array('pg_catalog', 'varchar')))
            ),
            new ColumnDefinition(
                new Identifier('temporary'), new TypeName(
                    new QualifiedName(array('pg_catalog', 'bit')),
                    new TypeModifierList(array(new Constant(1)))
                )
            )
        )));

        $this->assertEquals(new FromList(array($blah, $blahblah)), $list);
    }

    public function testJoins()
    {
        $list = $this->parser->parseFromList(<<<QRY
    a as aa natural join b bb left join (c right join d on (true = false)) as joinalias using (blah),
    f full outer join g(1) on false <> true cross join lateral (select 'blah') as h
QRY
        );

        $a = new RelationReference(new QualifiedName(array('a')));
        $a->setAlias(new Identifier('aa'));
        $b = new RelationReference(new QualifiedName(array('b')));
        $b->setAlias(new Identifier('bb'));
        $ab = new JoinExpression($a, $b, 'inner');
        $ab->setNatural(true);

        $cd = new JoinExpression(
            new RelationReference(new QualifiedName(array('c'))),
            new RelationReference(new QualifiedName(array('d'))),
            'right'
        );
        $cd->setOn(new OperatorExpression('=', new Constant(true), new Constant(false)));
        $cd->setAlias(new Identifier('joinalias'));

        $abcd = new JoinExpression($ab, $cd, 'left');
        $abcd->setUsing(new IdentifierList(array('blah')));

        $fg = new JoinExpression(
            new RelationReference(new QualifiedName(array('f'))),
            new RangeFunctionCall(new FunctionCall(
                new QualifiedName(array('g')), new FunctionArgumentList(array(new Constant(1))))
            ),
            'full'
        );
        $fg->setOn(new OperatorExpression('<>', new Constant(false), new Constant(true)));

        $select = new Select(new TargetList(array(new TargetElement(new Constant('blah')))));
        $subselect = new Subselect($select);
        $subselect->setAlias(new Identifier('h'));
        $subselect->setLateral(true);

        $this->assertEquals(new FromList(array($abcd, new JoinExpression($fg, $subselect, 'cross'))), $list);
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage Too many dots
     */
    public function testNoMoreThanTwoDots()
    {
        $this->parser->parseFromList('foo.bar.baz.quux');
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\SyntaxException
     * @expectedExceptionMessage should have an alias
     */
    public function testSubselectsRequireAnAlias()
    {
        $this->parser->parseFromList("(select 'foo')");
    }

    public function testWithOrdinality()
    {
        $list = $this->parser->parseFromList(
            'foo(1) with ordinality, bar(2) with ordinality as blah'
        );
        $foo = new RangeFunctionCall(new FunctionCall(
            new QualifiedName(array('foo')),
            new FunctionArgumentList(array(new Constant(1)))
        ));
        $foo->withOrdinality = true;
        $bar = new RangeFunctionCall(new FunctionCall(
            new QualifiedName(array('bar')),
            new FunctionArgumentList(array(new Constant(2)))
        ));
        $bar->setWithOrdinality(true);
        $bar->setAlias(new Identifier('blah'));

        $this->assertEquals(new FromList(array($foo, $bar)), $list);
    }

    public function testRowsFrom()
    {
        $list = $this->parser->parseFromList(<<<QRY
    rows from (foo(1) as (fooid integer, fooname text), foo(2)),
    rows from (generate_series(1, 5), generate_series(1,10)) with ordinality
QRY
        );
        $rowsOne = new RowsFrom(new RowsFromList(array(
            new RowsFromElement(
                new FunctionCall(
                    new QualifiedName(array('foo')), new FunctionArgumentList(array(new Constant(1)))
                ),
                new ColumnDefinitionList(array(
                    new ColumnDefinition(
                        new Identifier('fooid'), new TypeName(new QualifiedName(array('pg_catalog', 'int4')))
                    ),
                    new ColumnDefinition(
                        new Identifier('fooname'), new TypeName(new QualifiedName(array('text')))
                    ),
                ))
            ),
            new RowsFromElement(new FunctionCall(
                    new QualifiedName(array('foo')), new FunctionArgumentList(array(new Constant(2)))
            ))
        )));
        $rowsTwo = new RowsFrom(new RowsFromList(array(
            new RowsFromElement(new FunctionCall(
                new QualifiedName(array('generate_series')),
                new FunctionArgumentList(array(new Constant(1), new Constant(5)))
            )),
            new RowsFromElement(new FunctionCall(
                new QualifiedName(array('generate_series')),
                new FunctionArgumentList(array(new Constant(1), new Constant(10)))
            ))
        )));
        $rowsTwo->setWithOrdinality(true);

        $this->assertEquals(new FromList(array($rowsOne, $rowsTwo)), $list);
    }

    public function testTableSample()
    {
        $list = $this->parser->parseFromList(
<<<QRY
foo tablesample system (bar.baz * 100),
quux natural join xyzzy as a (b,c) tablesample bernoulli (50) repeatable (seed)
QRY
        );

        $sample1 = new TableSample(
            new RelationReference(new QualifiedName(array('foo'))),
            new QualifiedName(array('system')),
            new ExpressionList(array(
                new OperatorExpression(
                    '*',
                    new ColumnReference(array('bar', 'baz')),
                    new Constant(100)
                )
            ))
        );

        $sample2 = new JoinExpression(
            new RelationReference(new QualifiedName(array('quux'))),
            new TableSample(
                new RelationReference(new QualifiedName(array('xyzzy'))),
                new QualifiedName(array('bernoulli')),
                new ExpressionList(array(
                    new Constant(50)
                )),
                new ColumnReference(array('seed'))
            )
        );
        $sample2->setNatural(true);
        $sample2->right->setAlias(
            new Identifier('a'),
            new IdentifierList(array('b', 'c'))
        );

        $this->assertEquals(new FromList(array($sample1, $sample2)), $list);
    }

    public function testXmlTable()
    {
        // from docs
        $list = $this->parser->parseFromList(
<<<QRY
XMLTABLE(
    '//ROWS/ROW' PASSING data
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
    '/x:example/x:item' PASSING (SELECT data FROM xmldata)
    COLUMNS foo int PATH '@foo',
            bar int PATH '@B:bar'
) AS baz                        
QRY
        );

        $table1 = new XmlTable(
            new Constant('//ROWS/ROW'),
            new ColumnReference(array('data')),
            new XmlColumnList(array(
                new XmlColumnDefinition(
                    new Identifier('id'),
                    false,
                    new TypeName(new QualifiedName(array('pg_catalog', 'int4'))),
                    new Constant('@id')
                ),
                new XmlColumnDefinition(
                    new Identifier('ordinality'),
                    true
                ),
                new XmlColumnDefinition(
                    new Identifier('COUNTRY_NAME'),
                    false,
                    new TypeName(new QualifiedName(array('text')))
                ),
                new XmlColumnDefinition(
                    new Identifier('country_id'),
                    false,
                    new TypeName(new QualifiedName(array('text'))),
                    new Constant('COUNTRY_ID')
                ),
                new XmlColumnDefinition(
                    new Identifier('size_sq_km'),
                    false,
                    new TypeName(new QualifiedName(array('pg_catalog', 'float8'))),
                    new Constant('SIZE[@unit = "sq_km"]')
                ),
                new XmlColumnDefinition(
                    new Identifier('size_other'),
                    false,
                    new TypeName(new QualifiedName(array('text'))),
                    new Constant('concat(SIZE[@unit!="sq_km"], " ", SIZE[@unit!="sq_km"]/@unit)')
                ),
                new XmlColumnDefinition(
                    new Identifier('premier_name'),
                    false,
                    new TypeName(new QualifiedName(array('text'))),
                    new Constant('PREMIER_NAME'),
                    null,
                    new Constant('not specified')
                )
            ))
        );

        $subselect = new Select(new TargetList(array(new TargetElement(new ColumnReference(array('data'))))));
        $subselect->from[] = new RelationReference(new QualifiedName(array('xmldata')));
        $table2 = new XmlTable(
            new Constant('/x:example/x:item'),
            new SubselectExpression($subselect),
            new XmlColumnList(array(
                new XmlColumnDefinition(
                    new Identifier('foo'),
                    false,
                    new TypeName(new QualifiedName(array('pg_catalog', 'int4'))),
                    new Constant('@foo')
                ),
                new XmlColumnDefinition(
                    new Identifier('bar'),
                    false,
                    new TypeName(new QualifiedName(array('pg_catalog', 'int4'))),
                    new Constant('@B:bar')
                )
            )),
            new XmlNamespaceList(array(
                new XmlNamespace(new Constant('http://example.com/myns'), new Identifier('x')),
                new XmlNamespace(new Constant('http://example.com/b'), new Identifier('B'))
            ))
        );

        $table2->setLateral(true);
        $table2->setAlias(new Identifier('baz'));

        $this->assertEquals(new FromList(array($table1, $table2)), $list);
    }
}