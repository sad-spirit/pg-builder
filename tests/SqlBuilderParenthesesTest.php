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

use sad_spirit\pg_builder\SqlBuilderWalker,
    sad_spirit\pg_builder\nodes\ColumnReference,
    sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\Constant,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\expressions\BetweenExpression,
    sad_spirit\pg_builder\nodes\expressions\OperatorExpression,
    sad_spirit\pg_builder\nodes\expressions\PatternMatchingExpression;

/**
 * Abstract base class for tests checking the proper addition of parentheses
 *
 * We use a base class with two subclasses to easily notice which building mode fails
 */
abstract class SqlBuilderParenthesesTest extends \PHPUnit_Framework_TestCase
{
    /** @var SqlBuilderWalker */
    protected $builder;

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
     * Checks that we add parentheses for IS SOMETHING arguments in 'compat' mode and don't add in 'current'
     *
     * @param Node   $ast
     * @param string $expected
     */
    abstract public function testCompatParenthesesForIsPrecedenceChanges(Node $ast, $expected);

    /**
     * Checks that 'compat' mode properly adds parentheses to expressions with non-strict inequalities
     *
     * @param Node   $ast
     * @param string $expected
     */
    abstract public function testCompatParenthesesForInequalityPrecedenceChanges(Node $ast, $expected);

    /**
     * Checks that generated queries do not trigger a bug in pre-9.5 Postgres if 'compat' setting is used
     *
     * Multi-keyword operators beginning with NOT (e.g. NOT LIKE) had inconsistent
     * precedence, behaving like NOT with respect to their left operand but like
     * their base operator with respect to their right operand.
     *
     * @param Node $ast
     * @param $expected
     */
    abstract public function testCompatParenthesesForBuggyNotPrecedence(Node $ast, $expected);


    public function chainedComparisonProvider()
    {
        return array(
            array(
                new OperatorExpression(
                    '=',
                    new ColumnReference(array(new Identifier('foo'))),
                    new OperatorExpression(
                        '=',
                        new ColumnReference(array(new Identifier('bar'))),
                        new ColumnReference(array(new Identifier('baz')))
                    )
                ),
                'foo = (bar = baz)'
            ),
            array(
                new OperatorExpression(
                    '<=',
                    new OperatorExpression(
                        '>=',
                        new ColumnReference(array(new Identifier('a'))),
                        new ColumnReference(array(new Identifier('b')))
                    ),
                    new ColumnReference(array(new Identifier('c')))
                ),
                '(a >= b) <= c',
            ),
            array(
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
            )
        );
    }

    public function isPrecedenceProvider()
    {
        return array(
            array(
                new OperatorExpression(
                    'is null',
                    new OperatorExpression(
                        '=',
                        new Constant(false),
                        new Constant(true)
                    )
                ),
                '(false = true) is null'
            ),
            array(
                new OperatorExpression(
                    'is distinct from',
                    new OperatorExpression(
                        '@#!',
                        new ColumnReference(array(new Identifier('foo'))),
                        new ColumnReference(array(new Identifier('bar')))
                    ),
                    new ColumnReference(array(new Identifier('baz')))
                ),
                '(foo @#! bar) is distinct from baz'
            ),
            array(
                new OperatorExpression(
                    'is not true',
                    new PatternMatchingExpression(
                        new Constant('foo'),
                        new Constant('bar')
                    )
                ),
                "('foo' like 'bar') is not true"
            ),
            array(
                new OperatorExpression(
                    'is not false',
                    new BetweenExpression(
                        new ColumnReference(array(new Identifier('foo'))),
                        new Constant(false),
                        new Constant(true)
                    )
                ),
                '(foo between false and true) is not false'
            )
        );
    }

    public function inequalityPrecedenceProvider()
    {
        return array(
            array(
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
                ),
                "j ->> 'space' <= (j ->> 'node')"
            )
        );
    }

    public function buggyNotPrecedenceProvider()
    {
        return array(
            array(
                new OperatorExpression(
                    '=',
                    new Constant(true),
                    new PatternMatchingExpression(
                        new Constant('foo'),
                        new Constant('bar'),
                        'not like'
                    )
                ),
                "true = ('foo' not like 'bar')"
            )
        );
    }
}