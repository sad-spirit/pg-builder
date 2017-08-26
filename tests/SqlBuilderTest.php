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

use sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\Lexer,
    sad_spirit\pg_builder\SqlBuilderWalker;

/**
 * Tests building SQL from ASTs
 *
 * We assume that Parser works sufficiently well, so don't build ASTs by hand, but use
 * those created by Parser
 */
class SqlBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var SqlBuilderWalker
     */
    protected $builder;

    public function setUp()
    {
        $this->parser  = new Parser(new Lexer());
        $this->builder = new SqlBuilderWalker();
    }

    public function testBuildDeleteStatement()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
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
        $built = $parsed->dispatch($this->builder);
        $this->assertEquals(
            $parsed, $this->parser->parseStatement($built),
            'AST of the built statement should be equal to that of the original statement'
        );
    }

    public function testBuildInsertStatement()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
with foobar as (
    select idfoo, somefoo from foo
    except
    (select idbar, somebar from bar order by otherbar desc limit 5)
    order by 1 using #@%&!
    limit 10
)
insert into blah.blah
    (id, composite.field, ary[idx])
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
        $built = $parsed->dispatch($this->builder);
        $this->assertEquals(
            $parsed, $this->parser->parseStatement($built),
            'AST of the built statement should be equal to that of the original statement'
        );
    }

    public function testBuildSelectStatement()
    {
        $parsed = $this->parser->parseStatement($ts = <<<QRY
with xmlstuff as (
    select xmlelement(name foo, bar, 'content'), xmlelement(name blah, xmlattributes(baz, quux as xyzzy), 'content'),
       xmlexists('//foo[text() = ''bar'']' passing by ref '<blah><foo>bar</foo></blah>'),
       xmlforest(foo, 'bar' as baz),
       xmlparse(document xml.doc preserve whitespace),
       xmlpi(name php, 'echo ''Hello world!'';'),
       xmlroot(doc, version '1.2', standalone yes),
       xmlserialize(document foo as pg_catalog.text)
),
fnstuff as (
    select s.num,
        blah.foo(variadic a), blah.bar(a, variadic b), blah.baz(a, b := c, binary := d)
    from generate_series(1, 10) as s (num)
),
setopstuff (stuff) as (
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
)
select distinct on (something) quux.one, xyzzy.two[1], (quux.three).four, $1.blah, array[[1,2],[3,4]], row(3,4),
       1 + 2 * 3, (1 + 2) * 3, six between five and seven, quux.whatever is of (character varying, text, time with time zone),
       case when foo = 'bar' then 10 when foo = 'baz' then 100 else 1 end,
       'foo' collate bar.baz, '''whatever$$' noT ILIke 'quux' escape '!',
       cast (foo as text array[5]), foo::bar::baz(666), windowfn() over (win95), count(*) filter(where foo > 10),
       interval 'a value' minute to second (10), grouping(one, two, three, four)
from quux, xyzzy left join (atable as one left join anothertable as two using (commonfield))
                as three on xyzzy.id = three.xyzzy_id,
     some_function(1, 'two', array[3, 4]) with ordinality as sf (id integer, name text collate somecollation),
     (select five, six, seven from yetanothertable where id = $2) as ya,
     rows from (generate_series(1,5), generate_series(1,10) as (gs integer)) with ordinality,
     xyzzy as a (b,c) tablesample bernoulli (50) repeatable (seed)
where quux.id = ya.five and
      quux.id = xyzzy.quux_id or
      ya.six <= any(select stuff from setopstuff)
group by quux.one, four, grouping sets(cube((one, two), three), rollup(four, (five, six)), seven, ())
having count(quux.one) > over9000
window win95 as (partition by anything range between unbounded preceding and current row)
order by 1 using >>> nulls first, 2 desc
limit $3
offset $4
for no key update of quux, xyzzy
QRY
        );

        $built = $parsed->dispatch($this->builder);
        $this->assertEquals(
            $parsed, $this->parser->parseStatement($built),
            'AST of the built statement should be equal to that of the original statement'
        );
    }

    public function testBuildUpdateStatement()
    {
        $parsed = $this->parser->parseStatement(<<<QRY
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
        $built = $parsed->dispatch($this->builder);
        $this->assertEquals(
            $parsed, $this->parser->parseStatement($built),
            'AST of the built statement should be equal to that of the original statement'
        );
    }

    /**
     * @expectedException \sad_spirit\pg_builder\exceptions\InvalidArgumentException
     * @expectedExceptionMessage should not contain named parameters
     */
    public function testPreventNamedParameters()
    {
        $parsed = $this->parser->parseStatement('select somefoo from foo where idfoo = :id');
        $parsed->dispatch($this->builder);
    }
}