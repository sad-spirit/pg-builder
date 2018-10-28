# Changelog

## 0.2.3

* `Parser` clones parsed `Node` objects when storing them in cache or retrieving from cache. This prevents problems if the same query fragment is used multiple times.
* Make `ext-ctype` a required dependency in `composer.json`

## 0.2.2

* Invalid dollar-quoted strings created by `SqlBuilderWalker` if the string constant ended with dollar symbol (common thing with RegExps)
* Make `sad-spirit/pg_wrapper` a suggested dependency in `composer.json`

## 0.2.1

* Added support for `SKIP LOCKED` construct in locking clause of `SELECT`. Missed when implementing syntax of Postgres 9.5
* Added `BlankWalker` implementation of `TreeWalker` that only dispatches to child nodes. `ParameterWalker` is reimplemented as a subclass of `BlankWalker`.
* `$statement` property of `CommonTableExpression` is now writable.

## 0.2.0

* Support for new syntax added in PostgreSQL versions 9.5, 9.6 and 10
* `Parser` can be configured to use either pre-9.5 or 9.5+ operator precedence 
* `SqlBuilderWalker` can add parentheses in compatibility mode, so that generated queries will run on both pre-9.5 and 9.5+ PostgreSQL
* `sad-spirit/pg-wrapper` is now an optional dependency, base `Exception` interface no longer extends `sad_spirit\pg_wrapper\Exception`
* Added `converters\ParserAwareTypeConverterFactory` that contains code depending on `sad-spirit/pg-builder` classes
* `Parser` can now use any PSR-6 compatible cache implementation for storing generated ASTs, as home-grown cache implementation was removed from `pg_wrapper`.
* Correctly build `WHERE` clause when first call to `WhereOrHavingClause::and_()` contained an expression with `OR`

## 0.1.1

Exceptions from `sad-spirit/pg-wrapper` are no longer thrown here.

## 0.1.0

Initial release on GitHub