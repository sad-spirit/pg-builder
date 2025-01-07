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
    Node,
    enums\ConstantName,
    enums\IsPredicate,
    exceptions\SyntaxException
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    QualifiedName
};
use sad_spirit\pg_builder\nodes\expressions\{
    IsDistinctFromExpression,
    IsExpression,
    BetweenExpression,
    FunctionExpression,
    KeywordConstant,
    OperatorExpression,
    PatternMatchingExpression,
    StringConstant
};
use sad_spirit\pg_builder\nodes\lists\FunctionArgumentList;

/**
 * Operator precedence tests (checking that precedence follows Postgres 9.5+)
 */
class OperatorPrecedenceTest extends TestCase
{
    protected Parser $parser;

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
    protected function doTest(string $expression, $parsed): void
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

    #[DataProvider('associativeEqualityProvider')]
    public function testAssociativeEquality(string $expression, string|Node $parsedCurrent): void
    {
        $this->doTest($expression, $parsedCurrent);
    }

    #[DataProvider('inequalityPrecedenceProvider')]
    public function testInequalityPrecedence(string $expression, string|Node $parsedCurrent): void
    {
        $this->doTest($expression, $parsedCurrent);
    }

    #[DataProvider('inequalityWithCustomOperatorsPrecedenceProvider')]
    public function testInequalityWithCustomOperatorsPrecedence(string $expression, string|Node $parsedCurrent): void
    {
        $this->doTest($expression, $parsedCurrent);
    }

    #[DataProvider('isWhateverPrecedenceProvider')]
    public function testIsWhateverPrecedence(string $expression, string|Node $parsedCurrent): void
    {
        $this->doTest($expression, $parsedCurrent);
    }

    #[DataProvider('betweenPrecedenceProvider')]
    public function testBetweenPrecedence(string $expression, string|Node $parsedCurrent): void
    {
        $this->doTest($expression, $parsedCurrent);
    }

    #[DataProvider('equalsGreaterOperatorProvider')]
    public function testEqualsGreaterOperator(string $expression, string|Node $parsedCurrent): void
    {
        $this->doTest($expression, $parsedCurrent);
    }

    #[DataProvider('intervalTypeProvider')]
    public function testIntervalTypeSpecification(string $expression, string|Node $parsedCurrent): void
    {
        $this->doTest($expression, $parsedCurrent);
    }

    public static function associativeEqualityProvider(): array
    {
        return [
            [
                'foo = bar = baz',
                "Unexpected special character '='"
            ]
        ];
    }

    public static function inequalityPrecedenceProvider(): array
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


    public static function isWhateverPrecedenceProvider(): array
    {
        return [
            [
                'false = true is null',
                new IsExpression(
                    new OperatorExpression(
                        '=',
                        new KeywordConstant(ConstantName::FALSE),
                        new KeywordConstant(ConstantName::TRUE)
                    ),
                    IsPredicate::NULL
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
                    IsPredicate::TRUE,
                    true
                )
            ]
        ];
    }

    public static function betweenPrecedenceProvider(): array
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
                        new KeywordConstant(ConstantName::FALSE),
                        new KeywordConstant(ConstantName::TRUE)
                    ),
                    IsPredicate::FALSE,
                    true
                )
            ]
        ];
    }

    public static function inequalityWithCustomOperatorsPrecedenceProvider(): array
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

    public static function equalsGreaterOperatorProvider(): array
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

    public static function intervalTypeProvider(): array
    {
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
