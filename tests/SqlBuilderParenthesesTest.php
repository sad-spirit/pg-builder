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

use sad_spirit\pg_builder\SqlBuilderWalker;
use sad_spirit\pg_builder\nodes\ColumnReference;
use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\Constant;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\nodes\expressions\BetweenExpression;
use sad_spirit\pg_builder\nodes\expressions\OperatorExpression;
use sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression;

/**
 * Tests checking the proper addition of parentheses (should follow Postgres 9.5+ operator precedence)
 *
 * We use a base class with two subclasses to easily notice which building mode fails
 */
class SqlBuilderParenthesesTest extends \PHPUnit\Framework\TestCase
{
    /** @var SqlBuilderWalker */
    protected $builder;

    protected function setUp(): void
    {
        $this->builder = new SqlBuilderWalker();
    }

    private function _normalizeWhitespace($string)
    {
        return implode(' ', preg_split('/\s+/', trim($string)));
    }

    protected function assertStringsEqualIgnoringWhitespace($expected, $actual, $message = '')
    {
        $this->assertEquals($this->_normalizeWhitespace($expected), $this->_normalizeWhitespace($actual), $message);
    }

    /**
     * Checks that we always add parentheses if chains of comparison operators are used
     *
     * All comparison operators are non-associative in 9.5+ and have the same precedence,
     * so chains without parentheses will always fail
     *
     * @dataProvider chainedComparisonProvider
     * @param Node   $ast
     * @param string $expected
     */
    public function testChainedComparisonRequiresParentheses(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builder));
    }

    /**
     * Checks that we don't add parentheses for IS SOMETHING arguments
     *
     * @param Node   $ast
     * @param string $expected
     * @dataProvider isPrecedenceProvider
     */
    public function testCompatParenthesesForIsPrecedenceChanges(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builder));
    }

    /**
     * Checks that we don't adds parentheses to expressions with non-strict inequalities
     *
     * @param Node   $ast
     * @param string $expected
     * @dataProvider inequalityPrecedenceProvider
     */
    public function testCompatParenthesesForInequalityPrecedenceChanges(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builder));
    }

    public function chainedComparisonProvider()
    {
        return [
            [
                new OperatorExpression(
                    '=',
                    new ColumnReference([new Identifier('foo')]),
                    new OperatorExpression(
                        '=',
                        new ColumnReference([new Identifier('bar')]),
                        new ColumnReference([new Identifier('baz')])
                    )
                ),
                'foo = (bar = baz)'
            ],
            [
                new OperatorExpression(
                    '<=',
                    new OperatorExpression(
                        '>=',
                        new ColumnReference([new Identifier('a')]),
                        new ColumnReference([new Identifier('b')])
                    ),
                    new ColumnReference([new Identifier('c')])
                ),
                '(a >= b) <= c',
            ],
            [
                new OperatorExpression(
                    '=',
                    new OperatorExpression(
                        '<',
                        new Constant(2),
                        new Constant(3)
                    ),
                    new Constant(true)
                ),
                '(2 < 3) = true'
            ]
        ];
    }

    public function isPrecedenceProvider()
    {
        return [
            [
                new OperatorExpression(
                    'is null',
                    new OperatorExpression(
                        '=',
                        new Constant(false),
                        new Constant(true)
                    )
                ),
                'false = true is null'
            ],
            [
                new OperatorExpression(
                    'is distinct from',
                    new OperatorExpression(
                        '@#!',
                        new ColumnReference([new Identifier('foo')]),
                        new ColumnReference([new Identifier('bar')])
                    ),
                    new ColumnReference([new Identifier('baz')])
                ),
                'foo @#! bar is distinct from baz'
            ],
            [
                new OperatorExpression(
                    'is not true',
                    new PatternMatchingExpression(
                        new Constant('foo'),
                        new Constant('bar')
                    )
                ),
                "'foo' like 'bar' is not true"
            ],
            [
                new OperatorExpression(
                    'is not false',
                    new BetweenExpression(
                        new ColumnReference([new Identifier('foo')]),
                        new Constant(false),
                        new Constant(true)
                    )
                ),
                'foo between false and true is not false'
            ]
        ];
    }

    public function inequalityPrecedenceProvider()
    {
        return [
            [
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
                ),
                "j ->> 'space' <= j ->> 'node'"
            ]
        ];
    }
}
