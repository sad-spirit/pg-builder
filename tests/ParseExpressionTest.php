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
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    Select
};
use sad_spirit\pg_builder\exceptions\SyntaxException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Constant,
    OrderByElement,
    QualifiedOperator,
    Identifier,
    Indirection,
    Parameter,
    ArrayIndexes,
    TypeName,
    TargetElement,
    QualifiedName
};
use sad_spirit\pg_builder\nodes\range\RelationReference;
use sad_spirit\pg_builder\nodes\lists\{
    ExpressionList,
    FunctionArgumentList,
    TypeList,
    TargetList
};
use sad_spirit\pg_builder\nodes\expressions\{
    AtTimeZoneExpression,
    IsDistinctFromExpression,
    IsExpression,
    NotExpression,
    OverlapsExpression,
    RowExpression,
    ArrayExpression,
    InExpression,
    IsOfExpression,
    LogicalExpression,
    OperatorExpression,
    PatternMatchingExpression,
    BetweenExpression,
    CaseExpression,
    CollateExpression,
    FunctionExpression,
    SubselectExpression,
    TypecastExpression,
    WhenExpression,
    GroupingExpression
};

/**
 * Tests parsing all possible scalar expressions
 */
class ParseExpressionTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseExpressionAtoms()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    'foo', bar.baz, array[1,2], array[[1,2],[3,4]], row(3,4), $1.blah, :foo, null,
    grouping(a, b), ary[1:], ary[1]
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new Constant('foo'),
                new ColumnReference([new Identifier('bar'), new Identifier('baz')]),
                new ArrayExpression(new ExpressionList([new Constant(1), new Constant(2)])),
                new ArrayExpression(
                    [[new Constant(1), new Constant(2)], [new Constant(3), new Constant(4)]]
                ),
                new RowExpression([new Constant(3), new Constant(4)]),
                new Indirection([new Identifier('blah')], new Parameter(1)),
                new Parameter('foo'),
                new Constant(null),
                new GroupingExpression([
                    new ColumnReference([new Identifier('a')]),
                    new ColumnReference([new Identifier('b')])
                ]),
                new Indirection(
                    [new ArrayIndexes(new Constant(1), null, true)],
                    new ColumnReference([new Identifier('ary')])
                ),
                new Indirection(
                    [new ArrayIndexes(new Constant(1), null, false)],
                    new ColumnReference([new Identifier('ary')])
                )
            ]),
            $list
        );
    }

    public function testParentheses()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    (1), (2,3), (foo(4,5)).bar, (array[6,7])[1], ((select 1), 2), (select 1), ((((select 1)) order by 1) limit 1)
QRY
        );
        $select       = new Select(new TargetList([new TargetElement(new Constant(1))]));
        $selectParens = clone $select;
        $selectParens->order[] = new OrderByElement(new Constant(1));
        $selectParens->limit   = new Constant(1);

        $this->assertEquals(
            new ExpressionList([
                new Constant(1),
                new RowExpression([new Constant(2), new Constant(3)]),
                new Indirection(
                    [new Identifier('bar')],
                    new FunctionExpression(
                        new QualifiedName(['foo']),
                        new FunctionArgumentList([new Constant(4), new Constant(5)])
                    )
                ),
                new Indirection(
                    [new ArrayIndexes(new Constant(1))],
                    new ArrayExpression(new ExpressionList([new Constant(6), new Constant(7)]))
                ),
                new RowExpression([new SubselectExpression($select), new Constant(2)]),
                new SubselectExpression(clone $select),
                new SubselectExpression($selectParens)
            ]),
            $list
        );
    }

    /**
     * @dataProvider getUnbalancedParentheses
     * @param string $expr
     * @param string $message
     */
    public function testUnbalanceParentheses(string $expr, string $message)
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($expr);
    }

    public function getUnbalancedParentheses()
    {
        return [
            ['(foo', "Unbalanced '('"],
            ['(array[1,2)', "Unbalanced '['"]
        ];
    }

    public function testLogicalExpression()
    {
        $expr = $this->parser->parseExpression(<<<QRY
    a and not b or not not c and d or e
QRY
        );
        $this->assertEquals(
            new LogicalExpression(
                [
                    new LogicalExpression(
                        [
                            new ColumnReference([new Identifier('a')]),
                            new NotExpression(new ColumnReference([new Identifier('b')]))
                        ],
                        'and'
                    ),
                    new LogicalExpression(
                        [
                            new NotExpression(new NotExpression(new ColumnReference([new Identifier('c')]))),
                            new ColumnReference([new Identifier('d')])
                        ]
                    ),
                    new ColumnReference([new Identifier('e')])
                ],
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

        $this->expectException(SyntaxException::class);
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
            new OverlapsExpression(
                new RowExpression([
                    new ColumnReference([new Identifier('foo')]),
                    new ColumnReference([new Identifier('bar')])
                ]),
                new RowExpression([
                    new ColumnReference([new Identifier('baz')]),
                    new ColumnReference([new Identifier('quux')])
                ])
            ),
            $expr
        );

        $this->expectException(
            SyntaxException::class
        );
        $this->expectExceptionMessage('Wrong number of items');
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
                [
                    new BetweenExpression(
                        new ColumnReference([new Identifier('foo')]),
                        new Constant('bar'),
                        new Constant('baz')
                    ),
                    new BetweenExpression(
                        new ColumnReference([new Identifier('foofoo')]),
                        new Constant('quux'),
                        new Constant('xyzzy'),
                        'not between symmetric'
                    )
                ],
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

        $select = new Select(new TargetList([new TargetElement(new Constant('baz'))]));

        $this->assertEquals(
            new ExpressionList([
                new InExpression(
                    new InExpression(
                        new ColumnReference([new Identifier('foo')]),
                        new ExpressionList([
                            new Constant('foo'),
                            new Constant('bar')
                        ])
                    ),
                    new ExpressionList([
                        new Constant(true),
                        new Constant(false)
                    ])
                ),
                new InExpression(
                    new ColumnReference([new Identifier('bar')]),
                    $select,
                    'not in'
                )
            ]),
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

        $foo = new Select(new TargetList([new TargetElement(new ColumnReference(['otherfoo']))]));
        $foo->from[] = new RelationReference(new QualifiedName(['foosource']));

        $bar = new Select(new TargetList([new TargetElement(new ColumnReference(['barpattern']))]));
        $bar->from[] = new RelationReference(new QualifiedName(['barsource']));

        $this->assertEquals(
            new ExpressionList([
                new OperatorExpression(
                    '<',
                    new ColumnReference(['foo']),
                    new SubselectExpression($foo, 'any')
                ),
                new PatternMatchingExpression(
                    new ColumnReference(['bar']),
                    new SubselectExpression($bar, 'all'),
                    'like'
                ),
                new OperatorExpression(
                    '=',
                    new ColumnReference(['baz']),
                    new FunctionExpression('some', new FunctionArgumentList([
                        new ArrayExpression([new ColumnReference(['one']), new ColumnReference(['two'])])
                    ]))
                ),
                new OperatorExpression(
                    '=',
                    new OperatorExpression(
                        '=',
                        new ColumnReference([new Identifier('foo')]),
                        new FunctionExpression(
                            'any',
                            new FunctionArgumentList([
                                new ArrayExpression(new ExpressionList([
                                    new ColumnReference([new Identifier('bar')]),
                                    new ColumnReference([new Identifier('baz')])
                                ]))
                            ])
                        )
                    ),
                    new ColumnReference([new Identifier('quux')])
                )
            ]),
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
            new ExpressionList([
                new OperatorExpression(
                    '?',
                    new OperatorExpression(
                        '#',
                        new ColumnReference([new Identifier('w')]),
                        new OperatorExpression(
                            '@',
                            null,
                            new ColumnReference([new Identifier('v')])
                        )
                    ),
                    new ColumnReference([new Identifier('u')])
                ),
                new OperatorExpression(
                    '!',
                    new ColumnReference([new Identifier('q')]),
                    null
                ),
                new OperatorExpression(
                    '!',
                    null,
                    new ColumnReference([new Identifier('q')])
                ),
                new OperatorExpression(
                    new QualifiedOperator('blah', '###'),
                    new ColumnReference([new Identifier('r')]),
                    new ColumnReference([new Identifier('s')])
                ),
            ]),
            $list
        );
    }

    /**
     * @dataProvider getInvalidQualifiedOperators
     *
     * @param string|array $expression
     * @param string       $message
     */
    public function testInvalidQualifiedOperators($expression, string $message)
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage($message);

        if (is_array($expression)) {
            new QualifiedOperator(...$expression);
        } else {
            $this->parser->parseExpression($expression);
        }
    }

    public function getInvalidQualifiedOperators()
    {
        return [
            [['this', 'sucks'], 'does not look like a valid operator string'],
            ['foo operator(a.b.c.+) bar', 'Too many dots in qualified name'],
            ['foo operator(this.sucks) bar', "Unexpected special character ')'"]
        ];
    }

    public function testIsWhatever()
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo is null isnull, bar is not null notnull, 'foo' is distinct from 'bar',
    blah is of (character varying, text, time with time zone), 'xml' is not document,
    foobar is normalized, barbaz is not nfkc normalized
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new IsExpression(
                    new IsExpression(
                        new ColumnReference([new Identifier('foo')]),
                        IsExpression::NULL
                    ),
                    IsExpression::NULL
                ),
                new IsExpression(
                    new IsExpression(
                        new ColumnReference([new Identifier('bar')]),
                        IsExpression::NULL,
                        true
                    ),
                    IsExpression::NULL,
                    true
                ),
                new IsDistinctFromExpression(
                    new Constant('foo'),
                    new Constant('bar')
                ),
                new IsOfExpression(
                    new ColumnReference([new Identifier('blah')]),
                    new TypeList(
                        [
                            new TypeName(new QualifiedName(['pg_catalog', 'varchar'])),
                            new TypeName(new QualifiedName(['text'])),
                            new TypeName(new QualifiedName(['pg_catalog', 'timetz']))
                        ]
                    )
                ),
                new IsExpression(
                    new Constant('xml'),
                    IsExpression::DOCUMENT,
                    true
                ),
                new IsExpression(
                    new ColumnReference([new Identifier('foobar')]),
                    IsExpression::NORMALIZED
                ),
                new IsExpression(
                    new ColumnReference([new Identifier('barbaz')]),
                    IsExpression::NFKC_NORMALIZED,
                    true
                )
            ]),
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
            new ExpressionList([
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
            ]),
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
            new ExpressionList([
                new CaseExpression(
                    [
                        new WhenExpression(new Constant('bar'), new Constant(10)),
                        new WhenExpression(new Constant('baz'), new Constant(100))
                    ],
                    new Constant(1),
                    new ColumnReference(['foo'])
                ),
                new CaseExpression(
                    [
                        new WhenExpression(
                            new OperatorExpression('=', new ColumnReference(['foo']), new Constant('bar')),
                            new Constant(10)
                        ),
                        new WhenExpression(
                            new OperatorExpression('=', new ColumnReference(['foo']), new Constant('baz')),
                            new Constant(100)
                        )
                    ],
                    new Constant(1)
                )]),
            $list
        );
    }

    public function testCollate()
    {
        $this->assertEquals(
            new CollateExpression(new Constant('foo'), new QualifiedName(['bar', 'baz'])),
            $this->parser->parseExpression("'foo' collate bar.baz")
        );
    }

    public function testAtTimeZone()
    {
        $this->assertEquals(
            new AtTimeZoneExpression(new ColumnReference(['foo', 'bar']), new Constant('baz')),
            $this->parser->parseExpression("foo.bar at time zone 'baz'")
        );
    }

    public function testBogusPostfixOperatorBug()
    {
        $this->assertEquals(
            new OperatorExpression(
                '>=',
                new ColumnReference(['news_expire']),
                new TypecastExpression(
                    new Constant('now'),
                    new TypeName(new QualifiedName(['pg_catalog', 'date']))
                )
            ),
            $this->parser->parseExpression('news_expire >= current_date')
        );
    }
}
