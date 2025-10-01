<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\exceptions\SyntaxException;
use sad_spirit\pg_builder\Lexer;
use sad_spirit\pg_builder\Parser;

/**
 * Tests parsing RETURNING clause options
 */
class ParseReturningClauseTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Lexer());
    }

    public function testAllowAliasesOnlyForNewAndOld(): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage('expecting keyword');

        $this->parser->parseReturningClause('with (foo as bar) *');
    }

    public function testDisallowMultipleAliasesForNew(): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage('multiple times');

        $this->parser->parseReturningClause('with (new as n, old as o, new as nn) *');
    }

    public function testDisallowMultipleAliasesForOld(): void
    {
        $this::expectException(SyntaxException::class);
        $this::expectExceptionMessage('multiple times');

        $this->parser->parseReturningClause('with (old as o, new as n, old as oo) *');
    }
}
