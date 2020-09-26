# sad_spirit/pg_builder

[![Build Status](https://travis-ci.org/sad-spirit/pg-builder.svg?branch=master)](https://travis-ci.org/sad-spirit/pg-builder)

This is a query builder for Postgres with a twist: it contains a partial<sup>[1](#footnote1)</sup> reimplementation of PostgreSQL's own
query parser. This sets it aside from usual breed of "write-only" query builders:

* Almost all syntax available for `SELECT` (and `VALUES`) / `INSERT` / `UPDATE` / `DELETE` in PostgreSQL 13
  is supported, query being built is automatically checked for correct syntax.
* Query is represented as an Abstract Syntax Tree quite similar to PostgreSQL's internal representation.
* Query parts can be added to the AST either as objects or as strings (that will be processed by Parser).
* Nodes can be removed and replaced in AST.
* AST can be analyzed and transformed, the package takes advantage of this to allow named parameters like
  `:foo` instead of standard PostgreSQL's positional parameters `$1` and to infer parameters' types
  from SQL typecasts.

Parsing is definitely not a fast operation, so there are means to cache parts of AST and the resultant query.

## Usage example

```PHP
use sad_spirit\pg_builder\StatementFactory,
    sad_spirit\pg_builder\converters\ParserAwareTypeConverterFactory,
    sad_spirit\pg_builder\Select,
    sad_spirit\pg_wrapper\Connection;

$connection = new Connection('host=localhost user=username dbname=cms');
$factory    = new StatementFactory($connection);
// Needed for handling type info extracted from query
$connection->setTypeConverterFactory(new ParserAwareTypeConverterFactory($factory->getParser()));

// latest 5 news
/* @var $query Select */
$query      = $factory->createFromString(
    'select n.* from news as n order by news_added desc limit 5'
);

// we also need pictures for these...
$query->list[] = 'p.*';
$query->from[0]->leftJoin('pictures as p')->on = 'n.picture_id = p.picture_id';

// ...and need to limit them to only specific rubrics
$query->from[] = 'objects_rubrics as ro';
$query->where->and_('ro.rubric_id = any(:rubric::integer[]) and ro.obj_id = n.news_id');

// ...and keep 'em fresh
$query->where->and_('age(news_added) < :age::interval');

// $generated contains a query, mapping from named parameters to positional ones, types info
// it can be easily cached to prevent parsing/building SQL on each request
$generated = $factory->createFromAST($query);

// Note that we don't have to specify parameter types, these are extracted from query
$result = $generated->executeParams($connection, array(
    'rubric' => array(19, 20, 21),
    'age'    => 30 * 24 * 3600
));

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

## Documentation

Is in the [wiki](https://github.com/sad-spirit/pg-builder/wiki)

---
<a name="footnote1">1</a>: "Partial" here means the following: PostgreSQL grammar file `src/backend/parser/gram.y` is about 16K lines long. 
Of these about 3K lines are used for `SELECT` / `INSERT` / `UPDATE` / `DELETE` queries and are reimplemented here.
