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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\lists\FunctionArgumentList,
    sad_spirit\pg_builder\nodes\lists\TypeList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\Indirection,
    sad_spirit\pg_builder\nodes\Parameter,
    sad_spirit\pg_builder\nodes\expressions\RowExpression,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_builder\nodes\ArrayIndexes,
    sad_spirit\pg_builder\nodes\expressions\ArrayExpression,
    sad_spirit\pg_builder\nodes\expressions\InExpression,
    sad_spirit\pg_builder\nodes\expressions\IsOfExpression,
    sad_spirit\pg_builder\nodes\expressions\LogicalExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression,
    sad_spirit\pg_builder\nodes\expressions\BetweenExpression,
    sad_spirit\pg_builder\nodes\expressions\CaseExpression,
    sad_spirit\pg_builder\nodes\expressions\CollateExpression,
    sad_spirit\pg_builder\nodes\expressions\FunctionExpression,
    sad_spirit\pg_builder\nodes\expressions\SubselectExpression,
    sad_spirit\pg_builder\nodes\expressions\TypecastExpression,
    sad_spirit\pg_builder\nodes\expressions\WhenExpression,
    sad_spirit\pg_builder\nodes\TypeName,
    sad_spirit\pg_builder\nodes\TargetElement,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\range\RelationReference;

/**
 * Tests parsing all possible scalar expressions
 */
class ParseExpressionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseExpressionAtoms()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    'foo', bar.baz, array[1,2], array[[1,2],[3,4]], row(3,4), $1.blah, :foo, null
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new Constant('foo'),
                new ColumnReference(array(new Identifier('bar'), new Identifier('baz'))),
                new ArrayExpression(new ExpressionList(array(new Constant(1), new Constant(2)))),
                new ArrayExpression(
                    array(array(new Constant(1), new Constant(2)), array(new Constant(3), new Constant(4)))
                ),
                new RowExpression(array(new Constant(3), new Constant(4))),
                new Indirection(array(new Identifier('blah')), new Parameter(1)),
                new Parameter('foo'),
                new Constant(null)
            )),
            $list
        );
    }

    public function testParentheses()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    (1), (2,3), (foo(4,5)).bar, (array[6,7])[1], ((select 1), 2), (select 1)
QRY
        );
        $select = new Select(new TargetList(array(new TargetElement(new Constant(1)))));

        $this->assertEquals(
            new ExpressionList(array(
                new Constant(1),
                new RowExpression(array(new Constant(2), new Constant(3))),
                new Indirection(
                    array(new Identifier('bar')),
                    new FunctionExpression(
                        new QualifiedName(array('foo')),
                        new FunctionArgumentList(array(new Constant(4), new Constant(5)))
                    )
                ),
                new Indirection(
                    array(new ArrayIndexes(new Constant(1))),
                    new ArrayExpression(new ExpressionList(array(new Constant(6), new Constant(7))))
                ),
                new RowExpression(array(new SubselectExpression($select), new Constant(2))),
                new SubselectExpression(clone $select)
            )),
            $list
        );
    }

    /**
     * @dataProvider getUnbalancedParentheses
     */
    public function testUnbalanceParentheses($expr, $message)
    {
        $this->setExpectedException(
            'sad_spirit\pg_builder\exceptions\SyntaxException',
            $message
        );
        $this->parser->parseExpression($expr);
    }

    public function getUnbalancedParentheses()
    {
        return array(
            array('(foo', "Unbalanced '('"),
            array('(array[1,2)', "Unbalanced '['")
        );
    }

    public function testLogicalExpression()
    {
        $expr = $this->parser->parseExpression(<<<QRY
    a and not b or not not c and d or e
QRY
        );
        $this->assertEquals(
            new LogicalExpression(
                array(
                    new LogicalExpression(
                        array(
                            new ColumnReference(array(new Identifier('a'))),
                            new OperatorExpression('not', null, new ColumnReference(array(new Identifier('b'))))
                        ),
                        'and'
                    ),
                    new LogicalExpression(
                        array(
                            new OperatorExpression('not', null, new OperatorExpression(
                                'not', null, new ColumnReference(array(new Identifier('c')))
                            )),
                            new ColumnReference(array(new Identifier('d')))
                        )
                    ),
                    new ColumnReference(array(new Identifier('e')))
                ),
                'or'
            ),
            $expr
        );
    }

    public function testPatternMatching()
    {
        $expr = $this->parser->parseExpression(<<<QRY
    'foo' LIKE 'bar' > 'baz' noT ILIke 'quux' escape '!'
QRY
        );
        $this->assertEquals(
            new OperatorExpression(
                '>',
                new PatternMatchingExpression(
                    new Constant('foo'),
                    new Constant('bar'),
                    'like'
                ),
                new PatternMatchingExpression(
                    new Constant('baz'),
                    new Constant('quux'),
                    'not ilike',
                    new Constant('!')
                )
            ),
            $expr
        );

        $this->setExpectedException('sad_spirit\pg_builder\exceptions\SyntaxException');
        $this->parser->parseExpression(<<<QRY
    'foo' like 'bar' like 'baz'
QRY
        );
    }

    public function testOverlaps()
    {
        $expr = $this->parser->parseExpression(<<<QRY
    (foo, bar) overlaps row(baz, quux)
QRY
        );
        $this->assertEquals(
            new OperatorExpression(
                'overlaps',
                new RowExpression(array(
                    new ColumnReference(array(new Identifier('foo'))),
                    new ColumnReference(array(new Identifier('bar')))
                )),
                new RowExpression(array(
                    new ColumnReference(array(new Identifier('baz'))),
                    new ColumnReference(array(new Identifier('quux')))
                ))
            ),
            $expr
        );

        $this->setExpectedException(
            'sad_spirit\pg_builder\exceptions\SyntaxException',
            'Wrong number of parameters'
        );
        $this->parser->parseExpression(<<<QRY
    row(foo) overlaps (bar, baz)
QRY
        );
    }

    public function testBetween()
    {
        $expression = $this->parser->parseExpression(<<<QRY
    foo between 'bar' and 'baz' and foofoo NOT BETWEEN symmetric 'quux' and 'xyzzy'
QRY
        );
        $this->assertEquals(
            new LogicalExpression(
                array(
                    new BetweenExpression(
                        new ColumnReference(array(new Identifier('foo'))),
                        new Constant('bar'),
                        new Constant('baz')
                    ),
                    new BetweenExpression(
                        new ColumnReference(array(new Identifier('foofoo'))),
                        new Constant('quux'),
                        new Constant('xyzzy'),
                        'not between symmetric'
                    )
                ),
                'and'
            ),
            $expression
        );
    }

    public function testIn()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo in ('foo', 'bar') in (true, false), bar not in (select 'baz')
QRY
        );

        $select = new Select(new TargetList(array(new TargetElement(new Constant('baz')))));

        $this->assertEquals(
            new ExpressionList(array(
                new InExpression(
                    new InExpression(
                        new ColumnReference(array(new Identifier('foo'))),
                        new ExpressionList(array(
                            new Constant('foo'),
                            new Constant('bar')
                        ))
                    ),
                    new ExpressionList(array(
                        new Constant(true),
                        new Constant(false)
                    ))
                ),
                new InExpression(
                    new ColumnReference(array(new Identifier('bar'))),
                    $select,
                    'not in'
                )
            )),
            $list
        );
    }

    public function testSubqueryExpressions()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo < any(select otherfoo from foosource), bar like all(select barpattern from barsource),
    baz = some(array[one, two]), foo = any(array[bar,baz]) = quux
QRY
        );

        $foo = new Select(new TargetList(array(new TargetElement(new ColumnReference(array('otherfoo'))))));
        $foo->from[] = new RelationReference(new QualifiedName(array('foosource')));

        $bar = new Select(new TargetList(array(new TargetElement(new ColumnReference(array('barpattern'))))));
        $bar->from[] = new RelationReference(new QualifiedName(array('barsource')));

        $this->assertEquals(
            new ExpressionList(array(
                new OperatorExpression(
                    '<', new ColumnReference(array('foo')), new SubselectExpression($foo, 'any')
                ),
                new PatternMatchingExpression(
                    new ColumnReference(array('bar')), new SubselectExpression($bar, 'all'), 'like'
                ),
                new OperatorExpression(
                    '=', new ColumnReference(array('baz')),
                    new FunctionExpression('some', new FunctionArgumentList(array(
                        new ArrayExpression(array(new ColumnReference(array('one')), new ColumnReference(array('two'))))
                    )))
                ),
                new OperatorExpression(
                    '=',
                    new OperatorExpression(
                        '=',
                        new ColumnReference(array(new Identifier('foo'))),
                        new FunctionExpression(
                            'any',
                            new FunctionArgumentList(array(
                                new ArrayExpression(new ExpressionList(array(
                                    new ColumnReference(array(new Identifier('bar'))),
                                    new ColumnReference(array(new Identifier('baz')))
                                )))
                            ))
                        )
                    ),
                    new ColumnReference(array(new Identifier('quux')))
                )
            )),
            $list
        );
    }

    public function testGenericOperator()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    w # @ v ? u, q !, ! q, r operator(blah.###) s
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new OperatorExpression(
                    '?',
                    new OperatorExpression(
                        '#',
                        new ColumnReference(array(new Identifier('w'))),
                        new OperatorExpression(
                            '@',
                            null,
                            new ColumnReference(array(new Identifier('v')))
                        )
                    ),
                    new ColumnReference(array(new Identifier('u')))
                ),
                new OperatorExpression(
                    '!',
                    new ColumnReference(array(new Identifier('q'))),
                    null
                ),
                new OperatorExpression(
                    '!',
                    null,
                    new ColumnReference(array(new Identifier('q')))
                ),
                new OperatorExpression(
                    'operator("blah".###)',
                    new ColumnReference(array(new Identifier('r'))),
                    new ColumnReference(array(new Identifier('s')))
                ),
            )),
            $list
        );
    }

    public function testIsWhatever()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo is null isnull, bar is not null notnull, 'foo' is distinct from 'bar',
    blah is of (character varying, text, time with time zone), 'xml' is not document
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new OperatorExpression(
                    'is null',
                    new OperatorExpression(
                        'is null',
                        new ColumnReference(array(new Identifier('foo'))),
                        null
                    ),
                    null
                ),
                new OperatorExpression(
                    'is not null',
                    new OperatorExpression(
                        'is not null',
                        new ColumnReference(array(new Identifier('bar'))),
                        null
                    ),
                    null
                ),
                new OperatorExpression(
                    'is distinct from',
                    new Constant('foo'),
                    new Constant('bar')
                ),
                new IsOfExpression(
                    new ColumnReference(array(new Identifier('blah'))),
                    new TypeList(
                        array(
                            new TypeName(new QualifiedName(array('pg_catalog', 'varchar'))),
                            new TypeName(new QualifiedName(array('text'))),
                            new TypeName(new QualifiedName(array('pg_catalog', 'timetz')))
                        )
                    )
                ),
                new OperatorExpression(
                    'is not document',
                    new Constant('xml'),
                    null
                )
            )),
            $list
        );
    }

    public function testArithmetic()
    {
        $expr = $this->parser->parseExpressionList(<<<QRY
    1 + -2 * 3 ^ - 3 ^ 3 - 5 / 6
QRY
        );

        $this->assertEquals(
            new ExpressionList(array(
                new OperatorExpression(
                    '-',
                    new OperatorExpression(
                        '+',
                        new Constant(1),
                        new OperatorExpression(
                            '*',
                            new Constant(-2),
                            new OperatorExpression(
                                '^',
                                new OperatorExpression(
                                    '^',
                                    new Constant(3),
                                    new Constant(-3)
                                ),
                                new Constant(3)
                            )
                        )

                    ),
                    new OperatorExpression(
                        '/',
                        new Constant(5),
                        new Constant(6)
                    )
                )
            )),
            $expr
        );
    }

    public function testCaseExpression()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    case foo when 'bar' then 10 when 'baz' then 100 else 1 end,
    case when foo = 'bar' then 10 when foo = 'baz' then 100 else 1 end
QRY
        );
        $this->assertEquals(
            new ExpressionList(array(
                new CaseExpression(
                    array(
                        new WhenExpression(new Constant('bar'), new Constant(10)),
                        new WhenExpression(new Constant('baz'), new Constant(100))
                    ),
                    new Constant(1),
                    new ColumnReference(array('foo'))
                ),
                new CaseExpression(
                    array(
                        new WhenExpression(
                            new OperatorExpression('=', new ColumnReference(array('foo')), new Constant('bar')),
                            new Constant(10)
                        ),
                        new WhenExpression(
                            new OperatorExpression('=', new ColumnReference(array('foo')), new Constant('baz')),
                            new Constant(100)
                        )
                    ),
                    new Constant(1)
                ))
            ),
            $list
        );
    }

    public function testCollate()
    {
        $this->assertEquals(
            new CollateExpression(new Constant('foo'), new QualifiedName(array('bar', 'baz'))),
            $this->parser->parseExpression("'foo' collate bar.baz")
        );
    }

    public function testAtTimeZone()
    {
        $this->assertEquals(
            new OperatorExpression('at time zone', new ColumnReference(array('foo', 'bar')), new Constant('baz')),
            $this->parser->parseExpression("foo.bar at time zone 'baz'")
        );
    }

    public function testBogusPostfixOperatorBug()
    {
        $this->assertEquals(
            new OperatorExpression(
                '>=',
                new ColumnReference(array('news_expire')),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(new QualifiedName(array('pg_catalog', 'date')))
                )
            ),
            $this->parser->parseExpression('news_expire >= current_date')
        );
    }
}