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
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\expressions\BetweenExpression,
    sad_spirit\pg_builder\nodes\expressions\LogicalExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression;

/**
 * Abstract base class for operator precedence tests
 *
 * We use a base class with two subclasses to easily notice which parsing mode fails
 */
abstract class OperatorPrecedenceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    public function setUp()
    {
        $this->parser = new Parser(new Lexer());
    }

    /**
     * Checks that $expression parses to either of $parsedLegacy or $parsedCurrent depending on precedence setting
     *
     * @param string      $expression
     * @param string|Node $parsedLegacy
     * @param string|Node $parsedCurrent
     */
    protected function doTest($expression, $parsedLegacy, $parsedCurrent)
    {
        $parsed = Parser::OPERATOR_PRECEDENCE_PRE_9_5 === $this->parser->getOperatorPrecedence()
                  ? $parsedLegacy : $parsedCurrent;

        if (!is_string($parsed)) {
            $this->assertEquals($parsed, $this->parser->parseExpression($expression));

        } else {
            $this->setExpectedException(
                'sad_spirit\pg_builder\exceptions\SyntaxException', $parsed
            );
            $this->parser->parseExpression($expression);
        }
    }

    /**
     * @dataProvider associativeEqualityProvider
     * @param string      $expression
     * @param string|Node $parsedLegacy
     * @param string|Node $parsedCurrent
     */
    public function testAssociativeEquality($expression, $parsedLegacy, $parsedCurrent)
    {
        $this->doTest($expression, $parsedLegacy, $parsedCurrent);
    }

    /**
     * @dataProvider inequalityPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedLegacy
     * @param string|Node $parsedCurrent
     */
    public function testInequalityPrecedence($expression, $parsedLegacy, $parsedCurrent)
    {
        $this->doTest($expression, $parsedLegacy, $parsedCurrent);
    }

    /**
     * @dataProvider inequalityWithCustomOperatorsPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedLegacy
     * @param string|Node $parsedCurrent
     */
    public function testInequalityWithCustomOperatorsPrecedence($expression, $parsedLegacy, $parsedCurrent)
    {
        $this->doTest($expression, $parsedLegacy, $parsedCurrent);
    }

    /**
     * @dataProvider isWhateverPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedLegacy
     * @param string|Node $parsedCurrent
     */
    public function testIsWhateverPrecedence($expression, $parsedLegacy, $parsedCurrent)
    {
        $this->doTest($expression, $parsedLegacy, $parsedCurrent);
    }

    /**
     * @dataProvider betweenPrecedenceProvider
     * @param string      $expression
     * @param string|Node $parsedLegacy
     * @param string|Node $parsedCurrent
     */
    public function testBetweenPrecedence($expression, $parsedLegacy, $parsedCurrent)
    {
        $this->doTest($expression, $parsedLegacy, $parsedCurrent);
    }

    public function associativeEqualityProvider()
    {
        return array(
            array(
                'foo = bar = baz',
                new OperatorExpression(
                    '=',
                    new ColumnReference(array(new Identifier('foo'))),
                    new OperatorExpression(
                        '=',
                        new ColumnReference(array(new Identifier('bar'))),
                        new ColumnReference(array(new Identifier('baz')))
                    )
                ),
                "Unexpected special character '='"
            )
        );
    }

    public function inequalityPrecedenceProvider()
    {
        return array(
            array(
                'a < b and c = d > e',
                new LogicalExpression(
                    array(
                        new OperatorExpression(
                            '<',
                            new ColumnReference(array(new Identifier('a'))),
                            new ColumnReference(array(new Identifier('b')))
                        ),
                        new OperatorExpression(
                            '=',
                            new ColumnReference(array(new Identifier('c'))),
                            new OperatorExpression(
                                '>',
                                new ColumnReference(array(new Identifier('d'))),
                                new ColumnReference(array(new Identifier('e')))
                            )
                        )
                    ),
                    'and'
                ),
                "Unexpected special character '>'"
            ),
            // NB: in pre-9.5 Postgres '<=' and '>=' are treated as generic multicharacter operators,
            // so they are  left-associative and have higher precedence than '<' and '>'
            array(
                'a >= b <= c',
                new OperatorExpression(
                    '<=',
                    new OperatorExpression(
                        '>=',
                        new ColumnReference(array(new Identifier('a'))),
                        new ColumnReference(array(new Identifier('b')))
                    ),
                    new ColumnReference(array(new Identifier('c')))
                ),
                "Unexpected comparison operator '<='"
            ),
            array(
                'foo = bar > baz <= quux',
                new OperatorExpression(
                    '=',
                    new ColumnReference(array(new Identifier('foo'))),
                    new OperatorExpression(
                        '>',
                        new ColumnReference(array(new Identifier('bar'))),
                        new OperatorExpression(
                            '<=',
                            new ColumnReference(array(new Identifier('baz'))),
                            new ColumnReference(array(new Identifier('quux')))
                        )
                    )
                ),
                "Unexpected special character '>'"
            ),
            array(
                'a < b > c',
                "Unexpected special character '>'",
                "Unexpected special character '>'"
            )
        );
    }


    public function isWhateverPrecedenceProvider()
    {
        return array(
            array(
                'false = true is null',
                new OperatorExpression(
                    '=',
                    new Constant(false),
                    new OperatorExpression(
                        'is null',
                        new Constant(true)
                    )
                ),
                new OperatorExpression(
                    'is null',
                    new OperatorExpression(
                        '=',
                        new Constant(false),
                        new Constant(true)
                    )
                )
            ),
            array(
                'foo @#! bar is distinct from baz',
                new OperatorExpression(
                    '@#!',
                    new ColumnReference(array(new Identifier('foo'))),
                    new OperatorExpression(
                        'is distinct from',
                        new ColumnReference(array(new Identifier('bar'))),
                        new ColumnReference(array(new Identifier('baz')))
                    )
                ),
                new OperatorExpression(
                    'is distinct from',
                    new OperatorExpression(
                        '@#!',
                        new ColumnReference(array(new Identifier('foo'))),
                        new ColumnReference(array(new Identifier('bar')))
                    ),
                    new ColumnReference(array(new Identifier('baz')))
                )
            ),
            array(
                "'foo' like 'bar' is not true",
                new PatternMatchingExpression(
                    new Constant('foo'),
                    new OperatorExpression(
                        'is not true',
                        new Constant('bar')
                    )
                ),
                new OperatorExpression(
                    'is not true',
                    new PatternMatchingExpression(
                        new Constant('foo'),
                        new Constant('bar')
                    )
                )
            )
        );
    }

    public function betweenPrecedenceProvider()
    {
        return array(
            array(
                '1 between 0 and 2 between false and true',
                new BetweenExpression(
                    new BetweenExpression(
                        new Constant(1),
                        new Constant(0),
                        new Constant(2)
                    ),
                    new Constant(false),
                    new Constant(true)
                ),
                "Unexpected keyword 'between'"
            ),
            array(
                'foo between false and true is not false',
                "Unexpected keyword 'not'",
                new OperatorExpression(
                    'is not false',
                    new BetweenExpression(
                        new ColumnReference(array(new Identifier('foo'))),
                        new Constant(false),
                        new Constant(true)
                    )
                )
            )
        );
    }

    public function inequalityWithCustomOperatorsPrecedenceProvider()
    {
        // Based on Tom Lane's message to pgsql-hackers which started the whole precedence brouhaha
        // https://www.postgresql.org/message-id/12603.1424360914%40sss.pgh.pa.us
        return array(
            array(
                "j->>'space' <= j->>'node'",
                new OperatorExpression(
                    '->>',
                    new OperatorExpression(
                        '<=',
                        new OperatorExpression(
                            '->>',
                            new ColumnReference(array(new Identifier('j'))),
                            new Constant('space')
                        ),
                        new ColumnReference(array(new Identifier('j')))
                    ),
                    new Constant('node')
                ),
                new OperatorExpression(
                    '<=',
                    new OperatorExpression(
                        '->>',
                        new ColumnReference(array(new Identifier('j'))),
                        new Constant('space')
                    ),
                    new OperatorExpression(
                        '->>',
                        new ColumnReference(array(new Identifier('j'))),
                        new Constant('node')
                    )
                )
            )
        );
    }
}