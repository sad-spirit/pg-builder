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
 * Tests for proper addition of parentheses using both 'compat' and 'current' settings
 */
class SqlBuilderParenthesesTest extends \PHPUnit_Framework_TestCase
{
    /** @var SqlBuilderWalker */
    protected $builderCompat;
    /** @var SqlBuilderWalker */
    protected $builderCurrent;

    protected function setUp()
    {
        $this->builderCompat  = new SqlBuilderWalker(array('parentheses' => SqlBuilderWalker::PARENTHESES_COMPAT));
        $this->builderCurrent = new SqlBuilderWalker(array('parentheses' => SqlBuilderWalker::PARENTHESES_CURRENT));
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
     * @dataProvider chainedComparisonProvider
     * @param Node   $ast
     * @param string $expected
     */
    public function testChainedComparisonRequiresParentheses(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builderCompat));
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builderCurrent));
    }

    /**
     * @dataProvider isPrecedenceProvider
     * @param Node   $ast
     * @param string $expected
     */
    public function testCompatParenthesesForIsPrecedenceChanges(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builderCompat));
        $this->assertStringsEqualIgnoringWhitespace(
            str_replace(array('(', ')'), '', $expected),
            $ast->dispatch($this->builderCurrent)
        );
    }

    /**
     * @dataProvider inequalityPrecedenceProvider
     * @param Node   $ast
     * @param string $expected
     */
    public function testCompatParenthesesForInequalityPrecedenceChanges(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builderCompat));
        $this->assertStringsEqualIgnoringWhitespace(
            str_replace(array('(', ')'), '', $expected),
            $ast->dispatch($this->builderCurrent)
        );
    }

    /**
     * Checks that generated queries do not trigger a bug in pre-9.5 Postgres if 'compat' setting is used
     *
     * Multi-keyword operators beginning with NOT (e.g. NOT LIKE) had inconsistent
     * precedence, behaving like NOT with respect to their left operand but like
     * their base operator with respect to their right operand.
     *
     * @dataProvider buggyNotPrecedenceProvider
     * @param Node $ast
     * @param $expected
     */
    public function testCompatParenthesesForBuggyNotPrecedence(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builderCompat));
        $this->assertStringsEqualIgnoringWhitespace(
            str_replace(array('(', ')'), '', $expected),
            $ast->dispatch($this->builderCurrent)
        );
    }


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