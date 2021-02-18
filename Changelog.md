# Changelog

## [Unreleased]

### Added

* Improved Unicode support
  * `Lexer` handles `\uXXXX` and `\UXXXXXXXX` escapes in string literals with C-style escapes and converts them to UTF-8 strings.
  * `u&'...'` string literals and `u&"..."` identifiers are also supported, including trailing `UESCAPE` clauses. These are also converted to UTF-8.
  * `SqlBuilderWalker` has a new `'escape_unicode'` option that will trigger converting multi-byte UTF-8 characters (i.e. non-ASCII) to Unicode escapes in generated SQL.
* It is now possible to use the package to generate queries suitable for PDO
  * `StatementFactory` may be configured to keep `:foo` named parameters in generated SQL
  * Convenience method `StatementFactory::forPDO()` creates an instance of `StatementFactory` based on passed PDO connection
  * New `ParserAwareTypeConverterFactory::convertParameters()` method prepares parameters for `PDOStatement::execute()`
    using types info extracted from query
* Substantial performance improvements, especially when using cache. Tested on PHP 7.4 against version 0.4.1:
  * 15-20% faster SQL parsing
  * 25% faster SQL building
  * 25% faster unserialization of AST and 30% smaller serialized data length
  * 50% faster cloning of AST
* Tested and supported on PHP 8
* Static analysis with phpstan and psalm
* Special function-like constructs in `FROM` clause are now properly supported (Postgres allows these):
  ```SQL
  select * from current_date, cast('PT1M' as interval);
  ```
* Package `Exception` interface now extends `\Throwable`

### Changed
* Requires at least PHP 7.2
* Requires at least PostgreSQL 9.5
* Parser expects incoming SQL to be encoded in UTF-8, will throw exceptions in case of invalid encoding
* Method `mergeInputTypes()` of `NativeStatement` renamed to `mergeParameterTypes()`
* Changes to `StatementFactory` API
  * Constructor accepts an instance of `Parser`, an instance of class implementing new `StatementToStringWalker` interface
    (which is implemented by `SqlBuilderWalker`), and a flag to toggle PDO-compatible output.
  * Creation of `StatementFactory` configured for `Connection` object from `pg_wrapper` package
    is now done via `StatementFactory::forConnection()` method.
  * Similarly, an instance of `StatementFactory` configured for `PDO` connection can be created with `StatementFactory::forPDO()`.
* Changes to `Node` subclasses API: 
  * Methods `and_()` and `or_()` of `WhereOrHavingClause` were renamed to `and()` and `or()` as such names are allowed in PHP 7
  * Signatures of constructors for `ColumnReference` and `QualifiedName` were changed from `__construct(array $parts)` to `__construct(...$parts)`
  * `OperatorExpression` no longer accepts _any_ string as an operator, it accepts either a string of special characters valid for operator name or an instance
    of new `QualifiedOperator` class representing a namespaced operator: `operator(pg_catalog.+)`. 
    SQL constructs previously represented by `OperatorExpression` have their own nodes:
    `AtTimeZoneExpression`, `IsDistinctFromExpression`, `IsExpression`, `OverlapsExpression`, `NotExpression`.
  * Similar to above, `FunctionCall` constructor will no longer accept a string with an SQL keyword for a function name and will convert
    a non-keyword string to `QualifiedName`. Thus `FunctionCall`'s `$name` property is always an instance of `QualifiedName`.
    Function-like constructs previously represented by `FunctionCall` have their own nodes: `ArrayComparisonExpression` (for `ANY` / `ALL` / `SOME`),
    `NullIfExpression`, `SystemFunctionCallExpression` (for `COALESCE`, `GREATEST`, `LEAST`, `XMLCONCAT`)
  * `NOT` in SQL constructs like `IS NOT DISTINCT FROM` or `NOT BETWEEN` is represented as `$negated` property of a relevant `Node`
  * `Constant` and `Parameter` nodes were essentially 1:1 mapping of `TYPE_LITERAL` and `TYPE_PARAMETER` `Token`s exposing `Token::TYPE_*` constants,
    so branching code was needed to process them. Added specialized `KeywordConstant`, `NumericConstant`, `StringConstant`, 
    `NamedParameter`, `PositionalParameter` child classes. `Constant` and `Parameter` are now abstract base classes
    containing factory-like methods for creating child class instances.
  * `XmlColumnDefinition` (used in representing `XMLTABLE` constructs) is now an abstract class extended by `XmlOrdinalityColumnDefinition`
    and `XmlTypedColumnDefinition`
  * SQL standard "value functions" like `current_user` and `current_timestamp` are represented by a new `SQLValueFunction` Node
    instead of ad-hoc system function calls and typecasts. They will now appear in generated SQL the same way they did in source.
  * `ArrayIndexes` node representing a single offset access `[1]` rather than slice `[1:2]` will have that offset as an upper bound
    rather than lower. `ArrayIndexes` will disallow setting lower bound for non-slice nodes.
  * `RowsFrom` no longer extends `range\FunctionCall`, they both extend a new base `FunctionFromElement` class. Also property of `RowsFrom` 
    containing function calls is named `$functions` rather than `$function`.
* Changes to `TreeWalker` interface
  * `walkConstant()` was replaced by `walkKeywordConstant()` / `walkNumericConstant()` / `walkStringConstant()` methods.
  * Similarly, `walkParameter()` was replaced by `walkNamedParameter()` and `walkPositionalParameter()`.
  * Also `walkXmlColumnDefinition` was replaced by `walkXmlTypedColumnDefinition()` and `walkXmlOrdinalityColumnDefinition()`
  * Added `walkQualifiedOperator()` for visiting `QualifiedOperator` nodes.
  * Added `walkAtTimeZoneExpression()`, `walkIsDistinctFromExpression()`, `walkIsExpression()`, `walkNotExpression()`,
    `walkOverlapsExpression()` for visiting nodes previously represented by `OperatorExpression`.
  * Added `walkSQLValueFunction()` for visiting `SQLValueFunction` nodes.
  * Added `walkSystemFunctionCall()`, `walkArrayComparisonExpression()`, `walkNullIfExpression()` for visiting nodes previously
    represented by `FunctionCall`.
* Most of the required changes to your code can be automated by using the provided rector rules, [consult the upgrade instructions](Upgrading.md).
  Changes to custom `TreeWalker` implementations should be done manually, unfortunately.

### Removed
* Support for `mbstring.func_overload`, which was deprecated in PHP 7.2
* Support for operator precedence of pre-9.5 Postgres
* `'ascii_only_downcasing'` option for `Lexer`, it is now assumed to always be `true`

### Fixed
* Single quotes in string literals with C-style escapes could be unescaped twice
* Subselect with several parentheses around it could be incorrectly parsed
* `and()` and `or()` methods of `WhereOrHavingClause` incorrectly handled empty `WhereOrHavingClause` and empty `LogicalExpression` passed as arguments. 
* `BlankWalker` missed several `dispatch()` calls to child nodes

## [0.4.1] - 2020-09-30

### Fixed
* `SqlBuilderWalker` used incorrect operator precedence when generating SQL containing new `IS NORMALIZED` operator
* A sub-optimal regular expression in `Lexer` could cause up to 10x lexing slowdown for some queries  

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
[0.4.1]: https://github.com/sad-spirit/pg-builder/compare/v0.4.0...v0.4.1
[Unreleased]: https://github.com/sad-spirit/pg-builder/compare/v0.4.1...HEAD