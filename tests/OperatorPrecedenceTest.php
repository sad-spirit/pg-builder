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

use sad_spirit\pg_builder\nodes\ColumnReference;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\Lexer;
use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\Constant;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\nodes\IntervalTypeName;
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_builder\nodes\expressions\BetweenExpression;
use sad_spirit\pg_builder\nodes\expressions\FunctionExpression;
use sad_spirit\pg_builder\nodes\expressions\OperatorExpression;
use sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression;
use sad_spirit\pg_builder\nodes\lists\FunctionArgumentList;
use sad_spirit\pg_builder\nodes\lists\TypeModifierList;

/**
 * Operator precedence tests (checking that precedence follows Postgres 9.5+)
 */
class OperatorPrecedenceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    /**
     * Checks that $expression parses to either of $parsedLegacy or $parsedCurrent depending on precedence setting
     *
     * @param string      $expression
     * @param string|Node $parsed
     */
    protected function doTest($expression, $parsed)
    {
        if (!is_string($parsed)) {
            $this->assertEquals($parsed, $this->parser->parseExpression($expression));

        } else {
            $this->expectException(
                'sad_spirit\pg_builder\exceptions\SyntaxException'
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
    public function testAssociativeEquality($expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider inequalityPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testInequalityPrecedence($expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider inequalityWithCustomOperatorsPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testInequalityWithCustomOperatorsPrecedence($expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider isWhateverPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testIsWhateverPrecedence($expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider betweenPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testBetweenPrecedence($expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider equalsGreaterOperatorProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testEqualsGreaterOperator($expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    /**
     * @dataProvider intervalTypeProvider
     * @param string      $expression
     * @param string|Node $parsedCurrent
     */
    public function testIntervalTypeSpecification($expression, $parsedCurrent)
    {
        $this->doTest($expression, $parsedCurrent);
    }

    public function associativeEqualityProvider()
    {
        return [
            [
                'foo = bar = baz',
                "Unexpected special character '='"
            ]
        ];
    }

    public function inequalityPrecedenceProvider()
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


    public function isWhateverPrecedenceProvider()
    {
        return [
            [
                'false = true is null',
                new OperatorExpression(
                    'is null',
                    new OperatorExpression(
                        '=',
                        new Constant(false),
                        new Constant(true)
                    )
                )
            ],
            [
                'foo @#! bar is distinct from baz',
                new OperatorExpression(
                    'is distinct from',
                    new OperatorExpression(
                        '@#!',
                        new ColumnReference([new Identifier('foo')]),
                        new ColumnReference([new Identifier('bar')])
                    ),
                    new ColumnReference([new Identifier('baz')])
                )
            ],
            [
                "'foo' like 'bar' is not true",
                new OperatorExpression(
                    'is not true',
                    new PatternMatchingExpression(
                        new Constant('foo'),
                        new Constant('bar')
                    )
                )
            ]
        ];
    }

    public function betweenPrecedenceProvider()
    {
        return [
            [
                '1 between 0 and 2 between false and true',
                "Unexpected keyword 'between'"
            ],
            [
                'foo between false and true is not false',
                new OperatorExpression(
                    'is not false',
                    new BetweenExpression(
                        new ColumnReference([new Identifier('foo')]),
                        new Constant(false),
                        new Constant(true)
                    )
                )
            ]
        ];
    }

    public function inequalityWithCustomOperatorsPrecedenceProvider()
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
                        new ColumnReference([new Identifier('j')]),
                        new Constant('space')
                    ),
                    new OperatorExpression(
                        '->>',
                        new ColumnReference([new Identifier('j')]),
                        new Constant('node')
                    )
                )
            ]
        ];
    }

    public function equalsGreaterOperatorProvider()
    {
        return [
            [
                'foo => bar',
                "Unexpected named argument mark"
            ],
            [
                "foo(bar => 'baz')",
                new FunctionExpression(
                    new QualifiedName([new Identifier('foo')]),
                    new FunctionArgumentList([
                        'bar' => new Constant('baz')
                    ])
                )
            ]
        ];
    }

    public function intervalTypeProvider()
    {
        $interval = new IntervalTypeName(new TypeModifierList([
            new Constant(10)
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
