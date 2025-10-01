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

use sad_spirit\pg_builder\{
    Lexer,
    Node,
    Parser,
    Statement
};
use PHPUnit\Framework\TestCase;

/**
 * Ensures that BlankWalker visits all nodes
 */
class BlankWalkerTest extends TestCase
{
    /** @var string[] */
    private array $sql = [
        <<<'QRY'
with recursive w1 (w2, w3) as (
    select s1, s2, array[s3], false
    from s4 as s5
    where s6.s7 = $1
    union all
    select s8, s9.s10, s11 || s12, true
    from s13 s14, s15 s16
    where s17.s18 = s19.s20 
    order by s21.s22
) search depth first by w11, w12 set w13
  cycle w14, w15 set w16 to date 'tomorrow' default date 'yesterday' using w17,
w4 as (
    delete from only d1 as d2
    using d3.d4 as d5
    where d6.d7 in (select s23 from s24) and
          d8.d9 = 'blah'
    returning d10, d11, d12 as d13
), 
w5 as (
    insert into i1.i2
        (i3, i4.i5, i6[i7])
    overriding user value
    values
        (default, v1, (select s25 from s26 where s27 = 1)),
        (-1, v2, v3)
    on conflict (i8, (i9 || i10) collate i11 asc nulls last) where not i12 do update
        set i13 = i14.i15,
            i16 = i17.i18 || ' (formerly ' || i19.i20 || ')'
        where i21 is distinct from i22 + i23
    returning i24, nullif(i25, i26) as i27
), 
w6 (w7, w8) as (
    update u1 u2 
    set u3.u4 = 'blah', u5 = u6, (u7[u8], u9) = (u10, default),
           (u11, u12, u13) = (select s28, s29, s30 from s31)
    from u14
    where u15.u16 = u17.u18 and
          u19.u20 in (select s32 from s33)
    returning with (old as u21, new as u22) u23, u24 operator(u25.#@!) u26 as u27
), 
w9 as (
    select xmlelement(name x1, x2, 'content'), xmlelement(name x3, xmlattributes(x4, x5 as x6), x7),
       xmlexists(x8 passing by ref x9),
       xmlforest(x10, x11 as x12),
       xmlparse(document x13.x14 preserve whitespace),
       xmlpi(name x15, x16),
       xmlroot(x17, version x18, standalone yes),
       xmlserialize(document x19 as x20.x22)
),
w10 as materialized (
    select f1.f2(variadic f3), f4.f5(f6, variadic f7), f8.f9(f10, b := f11, binary := f12)
    from f13(1, f14) as f15 (f16)
),
w18 as (
    merge into m1 as m2
    using m3
    on m4.m5 is not distinct from m6
    when not matched and m7 = 2 then
        insert (m8) overriding system value values (m9)
    when matched and m10 <> 'quux' then
        update set m11 = 'xyzzy'
    when matched then 
        delete
    returning m12, merge_action()
)
select distinct on (e1) e2.e3, e4.e5[e6], (e7.e8).e9, $1.e10, array[[e11,2],[3,e12]], row(e13,:foo),
       1 + e14 * 3, (1 + e15) * 3, e16 between e17 and e18,
       case when e21 = e22 then e23 else e24 end,
       case e25 when e26 + e27 then e28.e29 end,
       e30 collate e31.e32, e33 noT ILIke e34 escape '!',
       cast (e135 as text array[5]), e35::e36::e137(666), f17() over (f18), count(*) filter(where f19 > 10),
       cast (e136 as interval minute to second (10)),
       grouping(g1, g2),
       e37.e38[:], e39.e40[e41:], e42.e43[: e44],
       e45 at time zone e46,
       (e47, 2) overlaps (e48, 3),
       e60 at local,
       current_user,
       extract(epoch from f20),
       overlay(f21 placing f22 from f23 for f24),
       position(f25 in f26),
       collation for(f27),
       substring(f28 from f29 for f30),
       substring(f31 similar f32 escape f33),
       trim(leading f34 from f35),
       normalize(f36, nfkc),
       xmlexists(f37 passing by value f38),
       f39 is json array with unique keys,
       json_objectagg(f40: f41 format json encoding "UtF32" absent on null with unique keys returning f42),
       json_arrayagg(f43 order by f44 null on null returning f45) filter (where f46 is not null) over (f47),
       json_object(f48: f49 returning f50),
       json_array(f51 returning f52),
       json(f53),
       json_scalar(f55),
       json_serialize(f57 returning f58),
       json_exists(f59, f60 passing f61 as f62),
       json_value(f64, f65 passing f66 as f67 returning f68 format json default f69 on empty default f70 on error),
       json_query(f71, f72 passing f73 as f74 returning f75 default f76 on empty default f77 on error),
       json_array(select f78 returning f79) 
from s34, s35 left join (s36 as s37 left join s38 as s39 using (s40))
                as s41 on s42.s43 = s44.s45,
     s46(s47, 'two', array[3, s48]) with ordinality as s49 (s50 integer, s51 s52 collate s53),
     (select s54, coalesce(s55, s56) from s57 where s58 = $2) as s59,
     rows from (s60(s61,5), s62(1,s63) as (s64 s65)) with ordinality,
     s66 as s67 (s68, s69) tablesample s70 (50) repeatable (s71),
     LATERAL XMLTABLE(
         XMLNAMESPACES(
             'http://example.com/myns' AS x23,
             'http://example.com/b' AS x24
         ),
         x25 PASSING by value (SELECT x26 FROM x27)
         COLUMNS x28 int PATH '@foo' not null default 'foo default',
                 x29 int PATH '@B:bar',
                 x30 FOR ORDINALITY
     ) AS s72,
     json_table(
         j1, j2 passing j3 as j4
         columns (
             j5 for ordinality,
             j6 j7 path '$' with wrapper default j21 on empty default j22 on error,
             j8 j9 format json encoding utf8 path '$' omit quotes default j23 on empty default j24 on error,
             j10 j11 exists path '$.aaa' true on error,
             nested path '$[1]' as j12 columns (
                j13 j14,
                nested path '$[*]' as j15 columns (
                    j16 j17
                ),
                j18 j19
            )
         )
     ) as j20,
     json_table('{"foo":"bar"}', '$' columns (foo text))
where s73.s74 <= any(array[s75, s76]) or
      not ((not e49) is false and e50)
group by g3.g4, g5, grouping sets(cube((g6, g7), g8), rollup(g9, (g10, g11)), g12, ())
having count(e51.e52) > e53
window e54 as (partition by e55 range between unbounded preceding and current row exclude group)
order by 1 using operator(e56.>>>) nulls first, e57 desc
limit e58
offset e59
for no key update of s77, s78 for share of s79 skip locked
QRY
    ];


    /**
     * Iterates over src/ directory and finds all non-abstract implementations of Node
     *
     * @phpstan-return class-string[]
     */
    private function createListOfAllConcreteNodeSubclasses(): array
    {
        $nodes  = [];
        if (false === $srcDir = \realpath(\dirname(__DIR__) . '/src')) {
            $this::fail('Could not open src directory');
        }

        /* @var \SplFileInfo $file */
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($srcDir),
                \RecursiveIteratorIterator::LEAVES_ONLY
            ) as $file
        ) {
            if (!$file->isFile() || !\preg_match('/\.php$/', (string) $file->getFilename())) {
                continue;
            }

            $className = \str_replace('/', '\\', \substr((string) $file->getRealPath(), \strlen($srcDir) + 1, -4));
            if (!\class_exists($className)) {
                if (!\interface_exists($className, false) && !\trait_exists($className, false)) {
                    $this::fail("Autoloading failed for {$className}");
                }
            } else {
                $reflectionClass = new \ReflectionClass($className);
                if ($reflectionClass->implementsInterface(Node::class) && !$reflectionClass->isAbstract()) {
                    $nodes[] = $className;
                }
            }
        }

        return $nodes;
    }

    private function assertAllNodeSubClassesAreUsed(Statement ...$ast): void
    {
        \preg_match_all('{"(sad_spirit\\\\pg_builder\\\\[^"]+)"}', \serialize($ast), $m);

        $notFound = \array_diff($this->createListOfAllConcreteNodeSubclasses(), $m[1]);

        $this::assertEmpty(
            $notFound,
            "Node subclasses not used in test query: " . \implode(', ', $notFound)
        );
    }

    /**
     * Checks that BlankWalker visits more or less all available nodes
     *
     * The test query should contain clauses that map to all possible Nodes, each of these clauses has Identifiers
     * where possible with unique easily-searchable names. We parse this query and perform two checks:
     *  - The resultant AST contains all non-abstract Node subclasses
     *  - List of Identifiers visited by BlankWalkerImplementation is the same as list of identifiers extracted
     *    from the query by a regexp
     */
    public function testBlankWalkerVisitsEverything(): void
    {
        $parser     = new Parser(new Lexer());
        // @phpstan-ignore callable.nonNativeMethod
        $statements = \array_map($parser->parseStatement(...), $this->sql);

        $this->assertAllNodeSubClassesAreUsed(...$statements);

        $walker = new BlankWalkerImplementation();
        \array_map(function ($statement) use ($walker): void {
            $statement->dispatch($walker);
        }, $statements);

        \preg_match_all('{' . $walker::IDENTIFIER_MASK . '}', \implode(" ", $this->sql), $m);
        $notFound = \array_diff($m[0], \array_keys($walker->identifiers));

        $this::assertEmpty(
            $notFound,
            "Names of Identifiers that weren't visited by BlankWalkerImplementation: " . \implode(', ', $notFound)
        );
    }
}
