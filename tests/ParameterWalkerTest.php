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
 *
 * @noinspection SqlNoDataSourceInspection, SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Parser,
    Lexer,
    ParameterWalker,
    SqlBuilderWalker,
    exceptions\InvalidArgumentException,
    nodes\QualifiedName,
    nodes\TypeName
};

/**
 * Unit test for ParameterWalker
 */
class ParameterWalkerTest extends TestCase
{
    protected Parser $parser;
    protected SqlBuilderWalker $builder;
    protected ParameterWalker $walker;

    protected function setUp(): void
    {
        $this->parser  = new Parser(new Lexer());
        $this->builder = new SqlBuilderWalker([
            'indent'    => '',
            'linebreak' => '',
            'wrap'      => null
        ]);
        $this->walker  = new ParameterWalker();
    }

    public function testDisallowMixedParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Mixing named and positional parameters is not allowed');
        $statement = $this->parser->parseStatement(<<<QRY
    select foo, bar from foosource where foo = :foo or bar = $1
QRY
        );
        $statement->dispatch($this->walker);
    }


    public function testReplaceNamedParametersInSelect(): void
    {
        $statement = $this->parser->parseStatement(<<<QRY
with blah as (
    select blahstuff from baseblah where blahid = :cte
)
select :target, :indirect.blah, :arraymember[1], foo[:arrayindex], (select foostuff from foo where fooid = :scalarsub),
    array[:array, anotherelement], row(:row, anothermember), 2 + :arithmetic * 3,
    somevalue between :between and somethingelse,
    case :caseargument when :whenclause then :thenclause else :caseelse end,
    :collate collate bar.baz, :pattern similar to 'quux', a_function(:scalarfnarg),
    :inleft in ('foo', 'bar'),
    cast(:typecast as text[]), :typecastop::foo::bar(666),
    xmlelement(name foo, :xmlelement, 'content'),
    xmlelement(name blah, xmlattributes(baz, :xmlattribute as xyzzy), 'content'),
    xmlexists(:xmlexists passing by ref '<blah><foo>bar</foo></blah>'),
    xmlforest(:xmlforest, 'bar' as baz),
    xmlparse(document :xmlparse preserve whitespace),
    xmlpi(name php, :xmlpi),
    xmlroot(:xmlroot, version '1.2', standalone yes),
    xmlserialize(document :xmlserialize as pg_catalog.text)
from some_function(:rangefnarg) as fn left join (
    select bazstuff from basebaz where bazid = :rangesub
) as baz on :onclause
where something or
      :logicalor and
      :logicaland
order by :orderby desc nulls last
limit :limit
offset :offset
QRY
        );
        $statement->dispatch($this->walker);

        $map   = $this->walker->getNamedParameterMap();
        $types = $this->walker->getParameterTypes();
        preg_match_all('#\$\d+#', (string) $statement->dispatch($this->builder), $matches);
        $this->assertCount(36, $map);
        $this->assertCount(36, $types);
        $this->assertCount(36, $matches[0]);

        $text = new TypeName(new QualifiedName('text'));
        $text->setBounds([-1]);
        $this->assertEquals($text, $types[$map['typecast']]);
        $this->assertEquals(new TypeName(new QualifiedName('foo')), $types[$map['typecastop']]);
    }

    public function testReplaceMultipleOccurences(): void
    {
        $statement = $this->parser->parseStatement(<<<QRY
select foo from foosource where foo ~ :foo
union all
select bar from barsource where bar ~ :foo
order by case  when :foo ~ 'foo' then 2 when :foo ~ 'bar' then 1 else 0 end desc
QRY
        );
        $statement->dispatch($this->walker);

        $this->assertCount(1, $this->walker->getNamedParameterMap());
        $this->assertCount(1, $this->walker->getParameterTypes());
        $this->assertEquals(4, substr_count((string) $statement->dispatch($this->builder), '$1'));
    }

    public function testReplaceParametersInUpdate(): void
    {
        $statement = $this->parser->parseStatement(<<<QRY
update foo set bar = :bar, baz = :baz where quux = :quux
QRY
        );
        $statement->dispatch($this->walker);

        $this->assertEquals(
            'update foo set bar = $1, baz = $2 where quux = $3',
            $statement->dispatch($this->builder)
        );
    }

    public function testReplaceParametersInInsert(): void
    {
        $statement = $this->parser->parseStatement(<<<QRY
insert into foo (bar, baz) values (default, :bar), (:baz, default) on conflict (shit) do update set baz = :baz
QRY
        );
        $statement->dispatch($this->walker);

        $this->assertEquals(
            'insert into foo (bar, baz) values (default, $1), ($2, default) on conflict (shit) do update set baz = $2',
            $statement->dispatch($this->builder)
        );
    }

    public function testLeavesNumericParametersAlone(): void
    {
        $statement = $this->parser->parseStatement('update foo set name = $1::text where id = $2');
        $statement->dispatch($this->walker);

        $result = $statement->dispatch($this->builder);
        $types  = $this->walker->getParameterTypes();
        $this->assertStringContainsString('$1', $result);
        $this->assertStringContainsString('$2', $result);
        $this->assertEquals(new TypeName(new QualifiedName('text')), $types[0]);
    }

    public function testLeaveNamedParametersAlone(): void
    {
        $statement = $this->parser->parseStatement('update foo set name = :name::text where id = :id');
        $walker    = new ParameterWalker(true);
        $statement->dispatch($walker);

        $this::assertDoesNotMatchRegularExpression('!\\$\\d!', $statement->dispatch($this->builder));
        $map   = $walker->getNamedParameterMap();
        $types = $walker->getParameterTypes();

        $this::assertEquals(new TypeName(new QualifiedName('text')), $types[$map['name']]);
    }
}
