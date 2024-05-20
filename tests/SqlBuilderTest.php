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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Lexer,
    Parser,
    SqlBuilderWalker
};
use sad_spirit\pg_builder\nodes\{
    expressions\StringConstant,
    lists\ExpressionList
};

/**
 * Tests building SQL from ASTs
 *
 * We assume that Parser works sufficiently well, so don't build ASTs by hand, but use
 * those created by Parser
 */
class SqlBuilderTest extends TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var SqlBuilderWalker
     */
    protected $builder;

    protected function setUp(): void
    {
        $this->parser  = new Parser(new Lexer());
        $this->builder = new SqlBuilderWalker();
    }

    protected function assertBuiltStatementProducesTheSameAST(string $sql): void
    {
        $parsed = $this->parser->parseStatement($sql);

        $built = $parsed->dispatch($this->builder);
        $this::assertEquals(
            $parsed,
            $this->parser->parseStatement($built),
            'AST of the built statement should be equal to that of the original statement'
        );

        $unserialized = unserialize(serialize($parsed));
        $this::assertEquals(
            $parsed,
            $unserialized,
            'AST of unserialized statement should be equal to that of the original'
        );
    }

    public function testBuildDeleteStatement(): void
    {
        $this->assertBuiltStatementProducesTheSameAST(<<<QRY
with recursive items (id, title, level, path) as (
    select item_id, item_title, 1,
           array[i.item_id]
    from tree_items as i
    where i.item_id = $1
    union all
    select i.item_id, i.item_title, pi.level + 1,
           pi.path || i.item_id
    from tree_items i, items pi
    where i.parent_id = pi.id order by i.item_id
)
delete from only tree_items as foo using item_properties as bar
where foo.item_id in (select id from items) and
      foo.item_id = bar.item_id and
      bar.property_type = 'blah'
returning *
QRY
        );
    }

    public function testBuildInsertStatement(): void
    {
        $this->assertBuiltStatementProducesTheSameAST(<<<QRY
with foobar as (
    select idfoo, somefoo from foo
    except
    (select idbar, somebar from bar order by otherbar desc limit 5)
    order by 1 using #@%&!
    limit 10
)
insert into blah.blah
    (id, composite.field, ary[idx])
overriding user value
values
    (default, 'foo', (select somefoo from foobar where idfoo = 1)),
    (-1, 'blah', 'duh-huh')
on conflict (id, (name || surname) collate "zz_ZZ" asc nulls last) where not blergh do update
    set name = excluded.name,
        surname = excluded.surname || ' (formerly ' || blah.surname || ')'
    where something is distinct from anything
returning *
QRY
        );
    }

    public function testBuildMergeStatement(): void
    {
        $this->assertBuiltStatementProducesTheSameAST(<<<'QRY'
with "null" as (
    select null as "null", 1 as one
)
merge into foo as bar
using "null"
on bar.id is not distinct from "null"
when not matched and one = 2 then
    insert (baz) overriding system value values ('quux')
when not matched and two = 1 then
    insert default values
when matched and baz <> 'quux' then
    update set baz = 'xyzzy'
when matched then 
    delete
QRY
        );
    }

    public function testBuildSelectStatement(): void
    {
        $this->assertBuiltStatementProducesTheSameAST(<<<'QRY'
with xmlstuff as (
    select xmlelement(name foo, bar, 'content'), xmlelement(name blah, xmlattributes(baz, quux as xyzzy), 'content'),
       xmlexists('//foo[text() = ''bar'']' passing by ref '<blah><foo>bar</foo></blah>'),
       xmlforest(foo, 'bar' as baz),
       xmlparse(document xml.doc preserve whitespace),
       xmlpi(name php, 'echo ''Hello world!'';'),
       xmlroot(doc, version '1.2', standalone yes),
       xmlserialize(document foo as pg_catalog.text indent)
),
fnstuff as materialized (
    select s.num,
        blah.foo(variadic a), blah.bar(a, variadic b), blah.baz(a, b := c, binary := d)
    from generate_series(1, 10) as s (num)
),
setopstuff (stuff) as not materialized (
    (
    select foostuff
    from foo
    union all
    select barstuff
    from bar
    )
    intersect
    select bazstuff
    from baz
    fetch first (3 + 2) rows with ties
) search breadth first by foostuff, barstuff set morestuff
  cycle barstuff, bazstuff set donealready to date 'tomorrow' default date 'yesterday' using somepath
select distinct on (something) quux.one, xyzzy.two[1], (quux.three).four, $1.blah, array[[1,2],[3,4]], row(3,4),
       1 + 2 * 3, (1 + 2) * 3, six between five and seven, eight not between nine and ten,
       case when foo = 'bar' then 10 when foo = 'baz' then 100 else 1 end,
       'foo' collate bar.baz, '''whatever$$' noT ILIke 'quux' escape '!',
       cast (foo as text array[5]), foo::bar::baz(666), windowfn() over (win95), count(*) filter(where foo > 10),
       interval 'a value' minute to second (10), grouping(one, two, three, four),
       xyzzy.arr[:], xyzzy.arr[2:], xyzzy.arr[:3],
       extract(epoch from ancient.times),
       overlay('foobar' placing 'baz' from 4 for 3),
       position('a' in 'foobar'),
       collation for(collatable),
       substring(), substring('a string' from 3 for 6), substring('a string' similar pattern escape '#'),
       trim(leading 'f' from 'foo'), trim(from 'foo', 'f', 'o'),
       normalize(abnormal, nfd),
       xmlexists('//foo[text() = ''bar'']' passing by ref ('<blah><foo>bar' || '</foo></blah>')),
       '{}' is json object with unique keys, 'blah' is not json,
       json_arrayagg(ary order by something returning blah) over (win95),
       json_objectagg(k: v null on null) filter (where v <> 0),
       json_array(values (2), (1), (3) returning bytea),
       json_object(k: v, kk value vv with unique keys)
from quux, xyzzy left join (atable as one left join anothertable as two using (commonfield) as usingalias)
                as three on xyzzy.id = three.xyzzy_id,
     some_function(1, 'two', array[3, 4]) with ordinality as sf (id integer, name text collate somecollation),
     (select five, six, seven from yetanothertable where id = $2) as ya,
     (select * from unaliased),
     rows from (generate_series(1,5), generate_series(1,10) as (gs integer)) with ordinality,
     xyzzy as a (b,c) tablesample bernoulli (50) repeatable (seed),
     LATERAL XMLTABLE(
         XMLNAMESPACES(
             'http://example.com/myns' AS x,
             'http://example.com/b' AS "B"
         ),
         '/x:example/x:item' PASSING by value (SELECT data FROM xmldata)
         COLUMNS foo int PATH '@foo' not null default 'foo default',
                 bar int PATH '@B:bar'
     ) AS baz
where quux.id = ya.five and
      quux.id = xyzzy.quux_id or
      ya.six <= any(select stuff from setopstuff) or
      not ((not not_precedence) is false and maybe)
group by distinct quux.one, four, grouping sets(cube((one, two), three), rollup(four, (five, six)), seven, ())
having count(quux.one) > over9000
window win95 as (partition by anything range between unbounded preceding and current row exclude group)
order by 1 using operator(detour.>>>) nulls first, 2 desc
limit $3
offset :foo
for no key update of quux, xyzzy for share of anothertable skip locked
QRY
        );
    }

    public function testBuildUpdateStatement(): void
    {
        $this->assertBuiltStatementProducesTheSameAST(<<<QRY
with foo as (
    select somefoo from basefoo
)
update bar baralias set blah.one = 'blah', blahblah = default, (baz[1], quux) = ('quux', default),
       (a, b, c) = (select aa, bb, cc from somewhere)
from baz
where baz.id = baralias.baz_id and
      baz.foovalue in (select somefoo from foo)
returning *
QRY
        );
    }

    public function testTrailingDollarInStringConstantBug(): void
    {
        $constants = new ExpressionList([
            new StringConstant('^\\d{3}-\\d{2}$'),
            new StringConstant('\'$$$_1')
        ]);
        $this->assertEquals(
            $constants,
            $this->parser->parseExpressionList(implode(', ', $constants->dispatch($this->builder)))
        );
    }

    /**
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     * @noinspection SqlCheckUsingColumns
     */
    public function testEscapeUnicode(): void
    {
        $ast = $this->parser->parseStatement(<<<QRY
    select молодой.слонок носатый, 'на лужайке '' какал \\ смачно'
    from "на"."тракторе" as Егорка join подкрался.незаметно using (большим, ковшом, """чугунным""")
    where схватил.слонка operator (за.#) жопу
    order by 😁.😬
QRY
        );
        $builder = new SqlBuilderWalker(['escape_unicode' => true]);
        $built   = $ast->dispatch($builder);

        $this::assertDoesNotMatchRegularExpression('/[\\x80-\\xff]/', $built, 'Built SQL should not contain non-ASCII symbols');
        $this::assertEquals($ast, $this->parser->parseStatement($built));
    }

    /**
     * Tests that everything from func_expr_common_subexpr production is handled correctly in FROM clause
     *
     * Postgres allows e.g. "select * from cast('now' as date), xmlpi(name php, 'echo ''Hello world!'';')", we parse
     * those expressions to Nodes that are not FunctionCall and need to ensure that
     *   - parsing succeeds
     *   - whatever is output with SqlBuilderWalker looks legit again
     *
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlResolve
     */
    public function testFunctionLikeConstructsInFromClauseBug(): void
    {
        $ast = $this->parser->parseStatement(<<<QRY
select *
from current_user,
     localtimestamp(1),
     cast('PT1M2S' as interval day to minute),
     nullif(foo, bar),
     coalesce(one, two, three),
     greatest(1, 2, 3, 4),
     least(4, 3, 2, 1),
     xmlconcat(x, m, l),
     xmlelement(name blah, xmlattributes(baz, quux as xyzzy), 'content'),
     xmlexists('//foo[text() = ''bar'']' passing by ref '<blah><foo>bar</foo></blah>'),
     xmlforest(foo, 'bar' as baz),
     xmlparse(document xml.doc preserve whitespace),
     xmlpi(name php, 'echo ''Hello world!'';'),
     xmlroot(doc, version '1.2', standalone yes),
     xmlserialize(document foo as pg_catalog.text),
     rows from (current_schema, cast('now' as date))
QRY
        );

        $built = $ast->dispatch($this->builder);
        $this->assertEquals(
            $ast,
            $this->parser->parseStatement($built),
            'AST of the built statement should be equal to that of the original statement'
        );
    }
}
