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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\tests;

use sad_spirit\pg_builder\SqlBuilderWalker,
    sad_spirit\pg_builder\Node;

/**
 * Tests for adding parentheses using 'compat' settings
 */
class SqlBuilderParenthesesCompatTest extends SqlBuilderParenthesesTest
{
    protected function setUp()
    {
        $this->builder = new SqlBuilderWalker(array('parentheses' => SqlBuilderWalker::PARENTHESES_COMPAT));
    }

    /**
     * @dataProvider isPrecedenceProvider
     * @inheritdoc
     */
    public function testCompatParenthesesForIsPrecedenceChanges(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builder));
    }

    /**
     * @dataProvider inequalityPrecedenceProvider
     * @inheritdoc
     */
    public function testCompatParenthesesForInequalityPrecedenceChanges(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builder));
    }

    /**
     * @dataProvider buggyNotPrecedenceProvider
     * @inheritdoc
     */
    public function testCompatParenthesesForBuggyNotPrecedence(Node $ast, $expected)
    {
        $this->assertStringsEqualIgnoringWhitespace($expected, $ast->dispatch($this->builder));
    }
}


