# Changelog

## [0.4.0] - 2020-09-26

This is the last feature release to support PHP 5 and Postgres versions below 9.5

### Added
* New keywords from Postgres 12 and 13
* Support for new syntax of Postgres 12:
  * `MATERIALIZED` / `NOT MATERIALIZED` modifiers of Common Table Expressions. Exposed as
    `$materialized` property of `CommonTableExpression` node.
  * `BY VALUE` clause in `XMLEXISTS` and `XMLTABLE`.
* Support for new syntax of Postgres 13:
  * `FETCH FIRST ... WITH TIES` form of SQL:2008 limit clause. Exposed as `$limitWithTies` property
    of `SelectCommon` node.
  * `IS [NOT] NORMALIZED` operator.
  * `NORMALIZE(...)` function.
* `.gitattributes` file to prevent installing tests with the package

### Changed
* Use composer's PSR-4 autoloaders for code and tests, drop home-grown ones

### Fixed
* Parsing of argument for SQL:2008 limit clause (`FETCH FIRST ...`) better follows behaviour of Postgres.  

## [0.3.0] - 2018-10-30

### Added
* Support for new syntax of PostgreSQL 11

### Fixed
* Ported fixes to multicharacter operators processing from Postgres lexer 
* Fixed `BlankWalker` (and consequently `ParameterWalker`) not visiting nodes representing `ON CONFLICT` clause of `INSERT`
* PHPDoc cleanup

## [0.2.3] - 2018-10-28

### Fixed
* `Parser` clones parsed `Node` objects when storing them in cache or retrieving from cache. This prevents problems if the same query fragment is used multiple times.
* Make `ext-ctype` a required dependency in `composer.json`

## [0.2.2] - 2017-10-05

### Fixed
* Invalid dollar-quoted strings created by `SqlBuilderWalker` if the string constant ended with dollar symbol (common thing with RegExps)
* Make `sad-spirit/pg_wrapper` a suggested dependency in `composer.json`

## [0.2.1] - 2017-09-19

### Added
* Support for `SKIP LOCKED` construct in locking clause of `SELECT`. Missed when implementing syntax of Postgres 9.5
* `BlankWalker` implementation of `TreeWalker` that only dispatches to child nodes. `ParameterWalker` is reimplemented as a subclass of `BlankWalker`.

### Changed
* `$statement` property of `CommonTableExpression` is now writable.

## [0.2.0] - 2017-09-04

### Added
* Support for new syntax of PostgreSQL versions 9.5, 9.6 and 10
* `Parser` can be configured to use either pre-9.5 or 9.5+ operator precedence 
* `SqlBuilderWalker` can add parentheses in compatibility mode, so that generated queries will run on both pre-9.5 and 9.5+ PostgreSQL
* Added `converters\ParserAwareTypeConverterFactory` that contains code depending on `sad-spirit/pg-builder` classes

### Changed
* `sad-spirit/pg-wrapper` is now an optional dependency, base `Exception` interface no longer extends `sad_spirit\pg_wrapper\Exception`
* `Parser` can now use any PSR-6 compatible cache implementation for storing generated ASTs, as home-grown cache implementation was removed from `pg_wrapper`.

### Fixed
* Correctly build `WHERE` clause when first call to `WhereOrHavingClause::and_()` contained an expression with `OR`

## [0.1.1] - 2014-10-06 

### Fixed
Exceptions from `sad-spirit/pg-wrapper` are no longer thrown here.

## [0.1.0] - 2014-09-28

Initial release on GitHub

[0.1.0]: https://github.com/sad-spirit/pg-builder/releases/tag/v0.1.0
[0.1.1]: https://github.com/sad-spirit/pg-builder/compare/v0.1.0...v0.1.1
[0.2.0]: https://github.com/sad-spirit/pg-builder/compare/v0.1.1...v0.2.0
[0.2.1]: https://github.com/sad-spirit/pg-builder/compare/v0.2.0...v0.2.1
[0.2.2]: https://github.com/sad-spirit/pg-builder/compare/v0.2.1...v0.2.2
[0.2.3]: https://github.com/sad-spirit/pg-builder/compare/v0.2.2...v0.2.3
[0.3.0]: https://github.com/sad-spirit/pg-builder/compare/v0.2.3...v0.3.0
[0.4.0]: https://github.com/sad-spirit/pg-builder/compare/v0.3.0...v0.4.0