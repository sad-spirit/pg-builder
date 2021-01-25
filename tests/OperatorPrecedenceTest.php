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
    Node
};
use sad_spirit\pg_builder\exceptions\SyntaxException;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    IntervalTypeName,
    QualifiedName
};
use sad_spirit\pg_builder\nodes\expressions\{
    IsDistinctFromExpression,
    IsExpression,
    BetweenExpression,
    FunctionExpression,
    KeywordConstant,
    NumericConstant,
    OperatorExpression,
    PatternMatchingExpression,
    StringConstant
};
use sad_spirit\pg_builder\nodes\lists\{
    FunctionArgumentList,
    TypeModifierList
};

/**
 * Operator precedence tests (checking that precedence follows Postgres 9.5+)
 */
class OperatorPrecedenceTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    /**
     * Checks that $expression parses to either of $parsedLegacy or $parsedCurrent depending on precedence setting
     *
     * @param string      $expression
     * @param string|Node $parsed
     */
    protected function doTest(string $expression, $parsed)
    {
        if (!is_string($parsed)) {
            $this->assertEquals($parsed, $this->parser->parseExpression($expression));

        } else {
            $this->expectException(
                SyntaxException::class
            );
            $this->expectExceptionMessage($parsed);
            $this->parser->parseExpression($expression);
        }
    }

    /**
     * @dataProvider associativeEqualityProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testAssociativeEquality(string $expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider inequalityPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testInequalityPrecedence(string $expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider inequalityWithCustomOperatorsPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testInequalityWithCustomOperatorsPrecedence(string $expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider isWhateverPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testIsWhateverPrecedence(string $expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider betweenPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testBetweenPrecedence(string $expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider equalsGreaterOperatorProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testEqualsGreaterOperator(string $expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider intervalTypeProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testIntervalTypeSpecification(string $expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    public function associativeEqualityProvider(): array
    {
        return [
            [
                'foo = bar = baz',
                "Unexpected special character '='"
            ]
        ];
    }

    public function inequalityPrecedenceProvider(): array
    {
        return [
            [
                'a < b and c = d > e',
                "Unexpected special character '>'"
            ],
            // NB: in pre-9.5 Postgres '<=' and '>=' are treated as generic multicharacter operators,
            // so they are  left-associative and have higher precedence than '<' and '>'
            [
                'a >= b <= c',
                "Unexpected comparison operator '<='"
            ],
            [
                'foo = bar > baz <= quux',
                "Unexpected special character '>'"
            ],
            [
                'a < b > c',
                "Unexpected special character '>'"
            ]
        ];
    }


    public function isWhateverPrecedenceProvider(): array
    {
        return [
            [
                'false = true is null',
                new IsExpression(
                    new OperatorExpression(
                        '=',
                        new KeywordConstant(KeywordConstant::FALSE),
                        new KeywordConstant(KeywordConstant::TRUE)
                    ),
                    IsExpression::NULL
                )
            ],
            [
                'foo @#! bar is distinct from baz',
                new IsDistinctFromExpression(
                    new OperatorExpression(
                        '@#!',
                        new ColumnReference(new Identifier('foo')),
                        new ColumnReference(new Identifier('bar'))
                    ),
                    new ColumnReference(new Identifier('baz'))
                )
            ],
            [
                "'foo' like 'bar' is not true",
                new IsExpression(
                    new PatternMatchingExpression(
                        new StringConstant('foo'),
                        new StringConstant('bar')
                    ),
                    IsExpression::TRUE,
                    true
                )
            ]
        ];
    }

    public function betweenPrecedenceProvider(): array
    {
        return [
            [
                '1 between 0 and 2 between false and true',
                "Unexpected keyword 'between'"
            ],
            [
                'foo between false and true is not false',
                new IsExpression(
                    new BetweenExpression(
                        new ColumnReference(new Identifier('foo')),
                        new KeywordConstant(KeywordConstant::FALSE),
                        new KeywordConstant(KeywordConstant::TRUE)
                    ),
                    IsExpression::FALSE,
                    true
                )
            ]
        ];
    }

    public function inequalityWithCustomOperatorsPrecedenceProvider(): array
    {
        // Based on Tom Lane's message to pgsql-hackers which started the whole precedence brouhaha
        // https://www.postgresql.org/message-id/12603.1424360914%40sss.pgh.pa.us
        return [
            [
                "j->>'space' <= j->>'node'",
                new OperatorExpression(
                    '<=',
                    new OperatorExpression(
                        '->>',
                        new ColumnReference(new Identifier('j')),
                        new StringConstant('space')
                    ),
                    new OperatorExpression(
                        '->>',
                        new ColumnReference(new Identifier('j')),
                        new StringConstant('node')
                    )
                )
            ]
        ];
    }

    public function equalsGreaterOperatorProvider(): array
    {
        return [
            [
                'foo => bar',
                "Unexpected named argument mark"
            ],
            [
                "foo(bar => 'baz')",
                new FunctionExpression(
                    new QualifiedName(new Identifier('foo')),
                    new FunctionArgumentList([
                        'bar' => new StringConstant('baz')
                    ])
                )
            ]
        ];
    }

    public function intervalTypeProvider(): array
    {
        $interval = new IntervalTypeName(new TypeModifierList([
            new NumericConstant('10')
        ]));
        $interval->setMask('minute to second');
        return [
            [
                "cast (foo as interval (5) minute to second (6))",
                "Unexpected keyword 'minute'"
            ],
            [
                "interval (10) 'a value' minute to second",
                "Unexpected keyword 'minute'"
            ]
        ];
    }
}
