# sad_spirit/pg_builder

[![Continuous Integration](https://github.com/sad-spirit/pg-builder/actions/workflows/continuous-integration.yml/badge.svg?branch=master)](https://github.com/sad-spirit/pg-builder/actions/workflows/continuous-integration.yml)

[![Static Analysis](https://github.com/sad-spirit/pg-builder/actions/workflows/static-analysis.yml/badge.svg?branch=master)](https://github.com/sad-spirit/pg-builder/actions/workflows/static-analysis.yml)

> Note: master branch contains code for an upcoming 3.0 version that requires PHP 8.2+ and supports new syntax of Postgres 17.
> 
> [Branch 2.x](../../tree/2.x) contains the stable version supporting PHP 7.2+ and Postgres 16.

This is a query builder for Postgres with a twist: it contains a partial<sup>[1](#footnote1)</sup> reimplementation of PostgreSQL's own
query parser. This sets it aside from the usual breed of "write-only" query builders:

* Query is represented as an Abstract Syntax Tree quite similar to PostgreSQL's internal representation.
* Query parts can be added to the AST either as objects or as strings (that will be processed by Parser).
* Nodes can be removed and replaced in AST.
* AST can be analyzed and transformed, the package takes advantage of this to allow named parameters like
  `:foo` instead of standard PostgreSQL's positional parameters `$1` and to infer parameters' types
  from SQL typecasts.
* Almost all syntax available for `SELECT` (and `VALUES`) / `INSERT` / `UPDATE` / `DELETE` / `MERGE` in PostgreSQL 17
  is supported, query being built is automatically checked for correct syntax.

Substantial effort was made to optimise parsing, but not parsing is faster anyway, so there are means to cache parts 
of AST and the resultant query.

## Usage example

```PHP
use sad_spirit\pg_builder\{
    Select,
    StatementFactory,
    converters\BuilderSupportDecorator
};
use sad_spirit\pg_wrapper\{
    Connection,
    converters\DefaultTypeConverterFactory
};

$wantPDO = false;

if ($wantPDO) {
    $pdo       = new \PDO('pgsql:host=localhost;user=username;dbname=cms');
    // Uses DB connection properties to set up parsing and building of SQL 
    $factory   = StatementFactory::forPDO($pdo);
    // NB: This still requires sad_spirit/pg_wrapper for type conversion code
    $converter = new BuilderSupportDecorator(new DefaultTypeConverterFactory(), $factory->getParser());
} else {
    $connection = new Connection('host=localhost user=username dbname=cms');
    // Uses DB connection properties to set up parsing and building of SQL 
    $factory    = StatementFactory::forConnection($connection);
    // Needed for handling type info extracted from query
    $connection->setTypeConverterFactory(new BuilderSupportDecorator(
        $connection->getTypeConverterFactory(),
        $factory->getParser()
    ));
}

// latest 5 news
/** @var Select $query */
$query      = $factory->createFromString(
    'select n.* from news as n order by news_added desc limit 5'
);

// we also need pictures for these...
$query->list[] = 'p.*';
$query->from[0]->leftJoin('pictures as p')->on = 'n.picture_id = p.picture_id';

// ...and need to limit them to only specific rubrics
$query->from[] = 'objects_rubrics as ro';
$query->where->and('ro.rubric_id = any(:rubric::integer[]) and ro.obj_id = n.news_id');

// ...and keep 'em fresh
$query->where->and('age(news_added) < :age::interval');

// $generated contains a query, mapping from named parameters to positional ones, types info
// it can be easily cached to prevent parsing/building SQL on each request
$generated = $factory->createFromAST($query);

// Note that we don't have to specify parameter types, these are extracted from query
if ($wantPDO) {
    $result = $pdo->prepare($generated->getSql());
    $result->execute($converter->convertParameters(
        $generated,
        [
            'rubric' => [19, 20, 21],
            'age'    => 30 * 24 * 3600        
        ]       
    ));
} else {
    $result = $generated->executeParams(
        $connection, 
        [
            'rubric' => [19, 20, 21],
            'age'    => 30 * 24 * 3600
        ]
    );
}


foreach ($result as $row) {
    print_r($row);
}

echo $generated->getSql();
```
the last `echo` statement will output something like
```SQL
select n.*, p.*
from news as n left join pictures as p on n.picture_id = p.picture_id, objects_rubrics as ro
where ro.rubric_id = any($1::pg_catalog.int4[])
    and ro.obj_id = n.news_id
    and age(news_added) < $2::interval
order by news_added desc
limit 5
```
if targeting `Connection` and something like
```SQL
select n.*, p.*
from news as n left join pictures as p on n.picture_id = p.picture_id, objects_rubrics as ro
where ro.rubric_id = any(:rubric::pg_catalog.int4[])
    and ro.obj_id = n.news_id
    and age(news_added) < :age::interval
order by news_added desc
limit 5
```
if targeting PDO

## Installation

Require the package with composer:
```
composer require "sad_spirit/pg_builder:^3"
```
pg_builder requires at least PHP 8.2. Either [native pgsql extension](https://php.net/manual/en/book.pgsql.php) with
[pg_wrapper](https://github.com/sad-spirit/pg-wrapper) package or [PDO](https://www.php.net/manual/en/book.pdo.php)
with pgsql support can be used to run the built queries.

Minimum supported PostgreSQL version is 12.

It is highly recommended to use [PSR-6 compatible](https://www.php-fig.org/psr/psr-6/) cache in production.

## Documentation

For in-depth description of package features, visit [pg_builder manual](https://pg-builder.readthedocs.io/).

---
<a name="footnote1">1</a>: "Partial" here means the following: PostgreSQL grammar file `src/backend/parser/gram.y` is about 19K lines long. 
Of these about 5K lines are used for `SELECT` / `INSERT` / `UPDATE` / `DELETE` / `MERGE` queries and are reimplemented here.
