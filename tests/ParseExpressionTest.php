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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    Select
};
use sad_spirit\pg_builder\enums\{
    ArrayComparisonConstruct,
    BetweenPredicate,
    ConstantName,
    IsJsonType,
    IsPredicate,
    LogicalOperator,
    PatternPredicate,
    SQLValueFunctionName,
    SubselectConstruct
};
use sad_spirit\pg_builder\exceptions\SyntaxException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    OrderByElement,
    QualifiedOperator,
    Identifier,
    Indirection,
    ArrayIndexes,
    TargetElement,
    QualifiedName
};
use sad_spirit\pg_builder\nodes\range\RelationReference;
use sad_spirit\pg_builder\nodes\lists\{
    ExpressionList,
    FunctionArgumentList,
    TargetList
};
use sad_spirit\pg_builder\nodes\expressions\{
    ArrayComparisonExpression,
    AtTimeZoneExpression,
    IsDistinctFromExpression,
    IsExpression,
    IsJsonExpression,
    KeywordConstant,
    NamedParameter,
    NotExpression,
    NumericConstant,
    OverlapsExpression,
    PositionalParameter,
    RowExpression,
    ArrayExpression,
    InExpression,
    LogicalExpression,
    OperatorExpression,
    PatternMatchingExpression,
    BetweenExpression,
    CaseExpression,
    CollateExpression,
    FunctionExpression,
    SQLValueFunction,
    StringConstant,
    SubselectExpression,
    WhenExpression,
    GroupingExpression
};

/**
 * Tests parsing all possible scalar expressions
 */
class ParseExpressionTest extends TestCase
{
    protected Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testParseExpressionAtoms(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    'foo', bar.baz, array[1,2], array[[1,2],[3,4]], row(3,4), $1.blah, :foo, null,
    grouping(a, b), ary[1:], ary[1]
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new StringConstant('foo'),
                new ColumnReference(new Identifier('bar'), new Identifier('baz')),
                new ArrayExpression(new ExpressionList([new NumericConstant('1'), new NumericConstant('2')])),
                new ArrayExpression(
                    [[new NumericConstant('1'), new NumericConstant('2')],
                     [new NumericConstant('3'), new NumericConstant('4')]]
                ),
                new RowExpression([new NumericConstant('3'), new NumericConstant('4')]),
                new Indirection([new Identifier('blah')], new PositionalParameter(1)),
                new NamedParameter('foo'),
                new KeywordConstant(ConstantName::NULL),
                new GroupingExpression([
                    new ColumnReference(new Identifier('a')),
                    new ColumnReference(new Identifier('b'))
                ]),
                new Indirection(
                    [new ArrayIndexes(new NumericConstant('1'), null, true)],
                    new ColumnReference(new Identifier('ary'))
                ),
                new Indirection(
                    [new ArrayIndexes(null, new NumericConstant('1'), false)],
                    new ColumnReference(new Identifier('ary'))
                )
            ]),
            $list
        );
    }

    public function testParentheses(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    (1), (2,3), (foo(4,5)).bar, (array[6,7])[1], ((select 1), 2), (select 1), ((((select 1)) order by 1) limit 1)
QRY
        );
        $select       = new Select(new TargetList([new TargetElement(new NumericConstant('1'))]));
        $selectParens = clone $select;
        $selectParens->order[] = new OrderByElement(new NumericConstant('1'));
        $selectParens->limit   = new NumericConstant('1');

        $this->assertEquals(
            new ExpressionList([
                new NumericConstant('1'),
                new RowExpression([new NumericConstant('2'), new NumericConstant('3')]),
                new Indirection(
                    [new Identifier('bar')],
                    new FunctionExpression(
                        new QualifiedName('foo'),
                        new FunctionArgumentList([new NumericConstant('4'), new NumericConstant('5')])
                    )
                ),
                new Indirection(
                    [new ArrayIndexes(null, new NumericConstant('1'))],
                    new ArrayExpression(new ExpressionList([new NumericConstant('6'), new NumericConstant('7')]))
                ),
                new RowExpression([new SubselectExpression($select), new NumericConstant('2')]),
                new SubselectExpression(clone $select),
                new SubselectExpression($selectParens)
            ]),
            $list
        );
    }

    #[DataProvider('getUnbalancedParentheses')]
    public function testUnbalanceParentheses(string $expr, string $message): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage($message);
        $this->parser->parseExpression($expr);
    }

    public static function getUnbalancedParentheses(): array
    {
        return [
            ['(foo', "Unbalanced '('"],
            ['(array[1,2)', "Unbalanced '['"]
        ];
    }

    public function testLogicalExpression(): void
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
                            new ColumnReference(new Identifier('a')),
                            new NotExpression(new ColumnReference(new Identifier('b')))
                        ],
                        LogicalOperator::AND
                    ),
                    new LogicalExpression(
                        [
                            new NotExpression(new NotExpression(new ColumnReference(new Identifier('c')))),
                            new ColumnReference(new Identifier('d'))
                        ]
                    ),
                    new ColumnReference(new Identifier('e'))
                ],
                LogicalOperator::OR
            ),
            $expr
        );
    }

    public function testPatternMatching(): void
    {
        $expr = $this->parser->parseExpression(<<<QRY
    'foo' LIKE 'bar' > 'baz' noT ILIke 'quux' escape '!'
QRY
        );
        $this->assertEquals(
            new OperatorExpression(
                '>',
                new PatternMatchingExpression(
                    new StringConstant('foo'),
                    new StringConstant('bar'),
                    PatternPredicate::LIKE
                ),
                new PatternMatchingExpression(
                    new StringConstant('baz'),
                    new StringConstant('quux'),
                    PatternPredicate::ILIKE,
                    true,
                    new StringConstant('!')
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

    public function testOverlaps(): void
    {
        $expr = $this->parser->parseExpression(<<<QRY
    (foo, bar) overlaps row(baz, quux)
QRY
        );
        $this->assertEquals(
            new OverlapsExpression(
                new RowExpression([
                    new ColumnReference(new Identifier('foo')),
                    new ColumnReference(new Identifier('bar'))
                ]),
                new RowExpression([
                    new ColumnReference(new Identifier('baz')),
                    new ColumnReference(new Identifier('quux'))
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

    public function testBetween(): void
    {
        $expression = $this->parser->parseExpression(<<<QRY
    foo between 'bar' and 'baz' and foofoo NOT BETWEEN symmetric 'quux' and 'xyzzy'
QRY
        );
        $this->assertEquals(
            new LogicalExpression(
                [
                    new BetweenExpression(
                        new ColumnReference(new Identifier('foo')),
                        new StringConstant('bar'),
                        new StringConstant('baz')
                    ),
                    new BetweenExpression(
                        new ColumnReference(new Identifier('foofoo')),
                        new StringConstant('quux'),
                        new StringConstant('xyzzy'),
                        BetweenPredicate::BETWEEN_SYMMETRIC,
                        true
                    )
                ],
                LogicalOperator::AND
            ),
            $expression
        );
    }

    public function testIn(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo in ('foo', 'bar') in (true, false), bar not in (select 'baz')
QRY
        );

        $select = new Select(new TargetList([new TargetElement(new StringConstant('baz'))]));

        $this->assertEquals(
            new ExpressionList([
                new InExpression(
                    new InExpression(
                        new ColumnReference(new Identifier('foo')),
                        new ExpressionList([
                            new StringConstant('foo'),
                            new StringConstant('bar')
                        ])
                    ),
                    new ExpressionList([
                        new KeywordConstant(ConstantName::TRUE),
                        new KeywordConstant(ConstantName::FALSE)
                    ])
                ),
                new InExpression(
                    new ColumnReference(new Identifier('bar')),
                    $select,
                    true
                )
            ]),
            $list
        );
    }

    public function testSubqueryExpressions(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo < any(select otherfoo from foosource), bar like all(select barpattern from barsource),
    baz = some(array[one, two]), foo = any(array[bar,baz]) = quux
QRY
        );

        $foo = new Select(new TargetList([new TargetElement(new ColumnReference('otherfoo'))]));
        $foo->from[] = new RelationReference(new QualifiedName('foosource'));

        $bar = new Select(new TargetList([new TargetElement(new ColumnReference('barpattern'))]));
        $bar->from[] = new RelationReference(new QualifiedName('barsource'));

        $this->assertEquals(
            new ExpressionList([
                new OperatorExpression(
                    '<',
                    new ColumnReference('foo'),
                    new SubselectExpression($foo, SubselectConstruct::ANY)
                ),
                new PatternMatchingExpression(
                    new ColumnReference('bar'),
                    new SubselectExpression($bar, SubselectConstruct::ALL),
                    PatternPredicate::LIKE
                ),
                new OperatorExpression(
                    '=',
                    new ColumnReference('baz'),
                    new ArrayComparisonExpression(
                        ArrayComparisonConstruct::SOME,
                        new ArrayExpression([new ColumnReference('one'), new ColumnReference('two')])
                    )
                ),
                new OperatorExpression(
                    '=',
                    new OperatorExpression(
                        '=',
                        new ColumnReference(new Identifier('foo')),
                        new ArrayComparisonExpression(
                            ArrayComparisonConstruct::ANY,
                            new ArrayExpression(new ExpressionList([
                                new ColumnReference(new Identifier('bar')),
                                new ColumnReference(new Identifier('baz'))
                            ]))
                        )
                    ),
                    new ColumnReference(new Identifier('quux'))
                )
            ]),
            $list
        );
    }

    public function testGenericOperator(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    w # @ v ? u, ! q, r operator(blah.###) s
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new OperatorExpression(
                    '?',
                    new OperatorExpression(
                        '#',
                        new ColumnReference(new Identifier('w')),
                        new OperatorExpression(
                            '@',
                            null,
                            new ColumnReference(new Identifier('v'))
                        )
                    ),
                    new ColumnReference(new Identifier('u'))
                ),
                new OperatorExpression(
                    '!',
                    null,
                    new ColumnReference(new Identifier('q'))
                ),
                new OperatorExpression(
                    new QualifiedOperator('blah', '###'),
                    new ColumnReference(new Identifier('r')),
                    new ColumnReference(new Identifier('s'))
                ),
            ]),
            $list
        );
    }

    #[DataProvider('getInvalidQualifiedOperators')]
    public function testInvalidQualifiedOperators(string|array $expression, string $message): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage($message);

        if (is_array($expression)) {
            new QualifiedOperator(...$expression);
        } else {
            $this->parser->parseExpression($expression);
        }
    }

    public static function getInvalidQualifiedOperators(): array
    {
        return [
            [['this', 'sucks'], 'does not look like a valid operator string'],
            ['foo operator(a.b.c.+) bar', 'Too many dots in qualified name'],
            ['foo operator(this.sucks) bar', "Unexpected special character ')'"]
        ];
    }

    public function testIsWhatever(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo is null isnull, bar is not null notnull, 'foo' is distinct from 'bar',
    'xml' is not document, foobar is normalized, barbaz is not nfkc normalized
QRY
        );
        $this->assertEquals(
            new ExpressionList([
                new IsExpression(
                    new IsExpression(
                        new ColumnReference(new Identifier('foo')),
                        IsPredicate::NULL
                    ),
                    IsPredicate::NULL
                ),
                new IsExpression(
                    new IsExpression(
                        new ColumnReference(new Identifier('bar')),
                        IsPredicate::NULL,
                        true
                    ),
                    IsPredicate::NULL,
                    true
                ),
                new IsDistinctFromExpression(
                    new StringConstant('foo'),
                    new StringConstant('bar')
                ),
                new IsExpression(
                    new StringConstant('xml'),
                    IsPredicate::DOCUMENT,
                    true
                ),
                new IsExpression(
                    new ColumnReference(new Identifier('foobar')),
                    IsPredicate::NORMALIZED
                ),
                new IsExpression(
                    new ColumnReference(new Identifier('barbaz')),
                    IsPredicate::NFKC_NORMALIZED,
                    true
                )
            ]),
            $list
        );
    }

    public function testIsJson(): void
    {
        $list = $this->parser->parseExpressionList(<<<QRY
    foo is json, bar is json without unique, baz is not json array, quux is json object with unique keys
QRY
        );
        $this::assertEquals(
            new ExpressionList([
                new IsJsonExpression(
                    new ColumnReference(new Identifier('foo'))
                ),
                new IsJsonExpression(
                    new ColumnReference(new Identifier('bar')),
                    false,
                    null,
                    false
                ),
                new IsJsonExpression(
                    new ColumnReference(new Identifier('baz')),
                    true,
                    IsJsonType::ARRAY
                ),
                new IsJsonExpression(
                    new ColumnReference(new Identifier('quux')),
                    false,
                    IsJsonType::OBJECT,
                    true
                )
            ]),
            $list
        );
    }

    public function testArithmetic(): void
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
                        new NumericConstant('1'),
                        new OperatorExpression(
                            '*',
                            new NumericConstant('-2'),
                            new OperatorExpression(
                                '^',
                                new OperatorExpression(
                                    '^',
                                    new NumericConstant('3'),
                                    new NumericConstant('-3')
                                ),
                                new NumericConstant('3')
                            )
                        )
                    ),
                    new OperatorExpression(
                        '/',
                        new NumericConstant('5'),
                        new NumericConstant('6')
                    )
                )
            ]),
            $expr
        );
    }

    public function testCaseExpression(): void
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
                        new WhenExpression(new StringConstant('bar'), new NumericConstant('10')),
                        new WhenExpression(new StringConstant('baz'), new NumericConstant('100'))
                    ],
                    new NumericConstant('1'),
                    new ColumnReference('foo')
                ),
                new CaseExpression(
                    [
                        new WhenExpression(
                            new OperatorExpression('=', new ColumnReference('foo'), new StringConstant('bar')),
                            new NumericConstant('10')
                        ),
                        new WhenExpression(
                            new OperatorExpression('=', new ColumnReference('foo'), new StringConstant('baz')),
                            new NumericConstant('100')
                        )
                    ],
                    new NumericConstant('1')
                )]),
            $list
        );
    }

    public function testCollate(): void
    {
        $this->assertEquals(
            new CollateExpression(new StringConstant('foo'), new QualifiedName('bar', 'baz')),
            $this->parser->parseExpression("'foo' collate bar.baz")
        );
    }

    public function testAtTimeZone(): void
    {
        $this->assertEquals(
            new AtTimeZoneExpression(new ColumnReference('foo', 'bar'), new StringConstant('baz')),
            $this->parser->parseExpression("foo.bar at time zone 'baz'")
        );
    }

    public function testBogusPostfixOperatorBug(): void
    {
        $this->assertEquals(
            new OperatorExpression(
                '>=',
                new ColumnReference('news_expire'),
                new SQLValueFunction(SQLValueFunctionName::CURRENT_DATE)
            ),
            $this->parser->parseExpression('news_expire >= current_date')
        );
    }

    public function testIsDistinctFromInequality(): void
    {
        $this->assertEquals(
            new IsDistinctFromExpression(
                new OperatorExpression('<', new NumericConstant('1'), new NumericConstant('2')),
                new OperatorExpression('>', new NumericConstant('1'), new NumericConstant('2'))
            ),
            $this->parser->parseExpression('1 < 2 is distinct from 1 > 2')
        );
    }
}
