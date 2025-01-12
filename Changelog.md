# Changelog

## [Unreleased]

The package now requires PHP 8.2+ and Postgres 12+.

### Added

* Tested on PHP 8.4 and Postgres 17
* Support for new syntax of Postgres 17
  * SQL/JSON functions
    * `json()` produces json values from text, bytea, json or jsonb values, represented by 
      `nodes\json\JsonConstructor`.
    * `json_scalar()` produces a json value from any scalar sql value, represented by
      `nodes\json\JsonScalar`.
    * `json_serialize()` produces text or bytea from json or jsonb input, represented by
      `nodes\json\JsonSerialize`
    * `json_exists()`, `json_query()`, `json_value()` for querying JSON data using jsonpath expressions.
      Represented by `nodes\json\JsonExists`, `nodes\json\JsonQuery`,
      `nodes\json\JsonValue`, respectively.
    * `json_table()` allows JSON data to be converted into a relational view and used a FROM clause,
      represented by `nodes\range\JsonTable` and related classes.
  * `AT LOCAL` expression for converting a timestamp to session time zone, represented by
    `nodes\expressions\AtLocalExpression`.

### Changed
 * Consistently follow Postgres 17 in what is considered a whitespace character: space, `\r`, `\n`, `\t`, `\v`, `\f`.
 * Native `public readonly` properties are used in `nodes\Identifier`, `nodes\expressions\Constant`,
   `nodes\expressions\Parameter`, and their subclasses instead of magic ones.
 * Enums are used throughout package instead of string constants. Migration TBD.

### Removed

 * Features deprecated in 2.x releases were removed:
   * `ParserAwareTypeConverterFactory` class, `BuilderSupportDecorator` should be used instead.
   * `$resultTypes` parameter for `NativeStatement::executePrepared()`. The types should be
     passed to `NativeStatement::prepare()`.
 * `Node` classes no longer implement deprecated `Serializable` interface.
 * `Keywords` class (replaced by `Keyword` enum).

### Fixed
 
 * Incorrect method was called to parse right argument of `IS [NOT] DISTINCT FROM` expression, causing syntax exceptions
   with correct expressions, e.g. `foo IS DISTINCT FROM bar < baz`.
 * Some keywords (`AND`, `BETWEEN`, `COLLATE`, `ILIKE`, `IN`, `IS`, `LIKE`, `OR`) that can be used in Postgres
   as column aliases without `AS` keyword caused syntax exceptions when used
   in such a way, e.g. `SELECT NULL AND` (this returns a null column aliased `and` in Postgres).
 * Lexer matches a complete identifier in "junk after number" tests. Previously only a single byte was matched,
   so the error message could possibly contain a partial multibyte character. Same fix as in Postgres 17.

## [2.4.0] - 2024-05-27

### Changed
 * `NativeStatement::executePrepared()` now uses `pg_wrapper`'s `PreparedStatement::executeParams()` under the hood,
   all parameter values should be passed in the `$params` argument. This was already the case when
   `NativeStatement` was built from a statement initially containing named parameters, but not positional ones.
 * `NativeStatement::prepare()` will fetch info on parameter types from the DB if some types are not given explicitly
   either in `$paramTypes` argument or via typecasts in the query itself. Previously these types were inferred from
   the types of parameter values on PHP side.
 * No longer use names deprecated in `pg_wrapper` 2.4.0: `ResultSet` -> `Result`, `Connection::getResource()` ->
  `Connection::getNative()`.

### Added
`NativeStatement::prepare()` now accepts `$resultTypes` argument that will be passed on to `Result` instances
returned by the created `PreparedStatement` instance.

### Deprecated
`$resultTypes` argument for `NativeStatement::executePrepared()` method, the types should be passed to `prepare()`.


## [2.3.1] - 2023-11-15

### Fixed
 * It is now possible to generate SQL suitable for `PDO::prepare()` even if a query does
   not contain placeholders, see [issue #15](https://github.com/sad-spirit/pg-builder/issues/15).
   Enabled by a new `$forcePDOPrepareCompatibility` argument to `StatementFactory::createFromAST()`.

## [2.3.0] - 2023-09-15

A stable release following release of Postgres 16. No code changes since beta.

## [2.3.0-beta] - 2023-08-30

### Added

Support for new syntax of PostgreSQL 16 (as of beta 3)
 * SQL/JSON functions and expressions:
   * `IS JSON` predicate represented by `nodes\expressions\IsJsonExpression`;
   * Aggregate functions `json_arrayagg()` and `json_objectagg()` represented by `nodes\json\JsonArrayAgg` and
     `nodes\json\JsonObjectAgg`;
   * Constructor functions `json_array()` and `json_object()` represented by
     `nodes\json\JsonArrayValueList`, `nodes\json\JsonArraySubselect`, `nodes\json\JsonObject` classes.
 * Allow non-decimal integer literals and underscores as separators in numeric literals.
 * Aliases for subqueries in `FROM` are now optional.
 * `SYSTEM_USER` server variable backed by `nodes\expressions\SQLValueFunction`.
 * `[NO] INDENT` option for `XMLSERIALIZE()` expression.


## [2.2.0] - 2023-05-14

### Added

* `TypeNameNodeHandler` interface extending `TypeConverterFactory` from `pg_wrapper` package: designed
  for factories that know how to process `TypeName` nodes. Its methods were defined previously in 
  `ParserAwareTypeConverterFactory` class which is now an implementation of the interface. 
* `BuilderSupportDecorator` class implementing `TypeNameNodeHandler` and working as a decorator around
  `DefaultTypeConverterFactory`.

### Deprecated

`ParserAwareTypeConverterFactory` is now deprecated, `BuilderSupportDecorator` should be used instead.


## [2.1.0] - 2022-11-04

A stable release following release of Postgres 15. No code changes since beta 2. 

## [2.1.0-beta.2] - 2022-10-09

### Removed
Support for SQL/JSON syntax as it was removed in [PostgreSQL 15 beta 4](https://www.postgresql.org/about/news/postgresql-15-beta-4-released-2507/)

## [2.1.0-beta] - 2022-08-18

### Added
Support for new syntax of PostgreSQL 15:
* `MERGE` statement
  * `Merge` class and `StatementFactory::merge()` helper method
* SQL/JSON functions and expressions
  * `IS JSON` predicate represented by `nodes\expressions\IsJsonExpression`;
  * Constructor functions `json()`, `json_scalar()`, `json_array()`, `json_object()` represented by
    `nodes\json\JsonConstructor`, `nodes\json\JsonScalar`, `nodes\json\JsonArray`, `nodes\json\JsonObject`
    classes respectively;
  * Aggregate functions `json_arrayagg()` and `json_objectagg()` represented by `nodes\json\JsonArrayAgg` and
    `nodes\json\JsonObjectAgg`;
  * Query functions `json_exists()`, `json_value()`, `json_query()` represented by `nodes\json\JsonExists`,
    `nodes\json\JsonValue`, `nodes\json\JsonQuery`;
  * `json_table()` expression appearing in `FROM` clause represented by `nodes\range\JsonTable`.

### Changed
Reject numeric literals and positional parameters with trailing non-digits: previously `SELECT 123abc` was parsed as
`SELECT 123 AS abc`, now it will throw a `SyntaxException`. This follows the changes to lexer done in Postgres 15.

## [2.0.1] - 2022-06-17

### Fixed
* Parser accepts `SELECT` queries with empty target lists (thanks to [@rvanvelzen](https://github.com/rvanvelzen) for [PR #14](https://github.com/sad-spirit/pg-builder/pull/14)).

## [2.0.0] - 2021-12-31

### Changed
* Update dependencies, prevent using incompatible versions
* When caching parsed query fragments, a version is added to cache key to prevent loading
  cache saved by previous version (e.g. `GroupByList` is now an abstract class and will cause an error if appearing in cache).

## [2.0.0-beta] - 2021-11-19

Updated for Postgres 14 and PHP 8.1. The major version is incremented due to a few BC breaks.

### Added
* Support for new syntax of PostgreSQL 14:
  * It is now possible to use most of the keywords as column aliases without `AS`.
  * `DISTINCT` clause for `GROUP BY`. Exposed as `$distinct` property for a new `GroupByClause` node
    which is now used for `$group` property of `Select`.
  * `SEARCH` and `CYCLE` clauses for Common Table Expressions. Implemented as `SearchClause` and `CycleClause` classes and
    exposed as `$search` and `$cycle` properties of `CommonTableExpression` class.
  * Alias can be specified for `USING` clause of `JOIN` expression. Exposed as `$alias` property of a new
    `UsingClause` node which is now used for `$using` property of `JoinExpression`.
  * `SUBSTRING(string SIMILAR pattern ESCAPE escape)` function call, represented by `nodes\expressions\SubstringSimilarExpression`

### Changed
Several SQL standard functions with special arguments format (arguments separated by keywords or keywords as arguments, etc) were
previously parsed into `FunctionExpression` with `pg_catalog.internal_name` for a function name and a standard argument list,
they appeared that way in generated SQL: `trim(trailing 'o' from 'foo')` -> `pg_catalog.rtrim('foo', 'o')`.
These functions are now represented by separate `Node` subclasses and will appear in generated SQL the same way they did in source. 
The following functions were affected:
* `COLLATION FOR(...)` is now represented by `nodes\expressions\CollationForExpression`
* `EXTRACT(field FROM source)` - `nodes\expressions\ExtractExpression`
* `NORMALIZE(...)` - `nodes\expressions\NormalizeExpression`
* `OVERLAY(...)` - `nodes\expressions\OverlayExpression`
* `POSITION(...)` - `nodes\expressions\PositionExpression`
* `SUBSTRING(... FROM ...)` - `nodes\expressions\SubstringFromExpression`
* `TRIM(...)` - `nodes\expressions\TrimExpression`
* `XMLEXISTS(...)` - `nodes\xml\XmlExists`

This follows the changes done in Postgres 14. Note also that `EXTRACT()` function maps to internal `pg_catalog.extract()` 
in Postgres 14 while in previous versions it mapped to `pg_catalog.date_part()`.

### Fixed
The package will run under PHP 8.1 with no `E_DEPRECATED` messages:
* Added return type hints to implementations of methods from `ArrayAccess`, `Countable`, and `IteratorAggregate` interfaces.
* Implemented `__serialize()` and `__unserialize()` magic methods alongside methods defined in deprecated `Serializable` interface.

### Removed
* Support for `IS [NOT] OF` expressions
* Support for postfix operators

## [1.0.2] - 2021-08-10

### Fixed
A space before `NOT` was missing when generating `NOT BETWEEN` expressions.

## [1.0.1] - 2021-07-13

### Fixed
`recursive` property of `WithClause` was not properly set when passing SQL strings to its `merge()` and `replace()` methods. 

## [1.0.0] - 2021-06-26

### Deprecated
Support for undocumented `IS [NOT] OF` expression that will be removed in Postgres 14.

## [1.0.0-beta] - 2021-02-21

### Added

* Improved Unicode support
  * `Lexer` handles `\uXXXX` and `\UXXXXXXXX` escapes in string literals with C-style escapes and converts them to UTF-8 strings.
  * `u&'...'` string literals and `u&"..."` identifiers are also supported, including trailing `UESCAPE` clauses. These are also converted to UTF-8.
  * `SqlBuilderWalker` has a new `'escape_unicode'` option that will trigger converting multi-byte UTF-8 characters (i.e. non-ASCII) to Unicode escapes in generated SQL.
* It is now much easier to use the package for generating queries suitable for PDO:
  * `StatementFactory` may be configured to keep `:foo` named parameters, automatically escape `?` in operator names 
    and prevent generating dollar-quoted strings so that resultant SQL can be handed to `PDO::prepare()`
  * Convenience method `StatementFactory::forPDO()` creates an instance of `StatementFactory` based on passed PDO 
    connection (and enables the above compatibility)
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
  * `NOT` in SQL constructs like `IS NOT DISTINCT FROM` or `NOT BETWEEN` is represented as `$not` property of a relevant `Node`
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
* Most of the required renames and changes to code that deals with creating objects can be automated by using the 
  provided rector rules, [consult the upgrade instructions](Upgrading.md). Changes to custom `TreeWalker` implementations 
  and to code that checks for particular object instances and property values should be done manually, unfortunately.

### Deprecated
* As postfix operators are deprecated in Postgres 13 and will be removed in Postgres 14, `OperatorExpression` will
  now trigger a (silenced) `E_USER_DEPRECATED` error when right operand is missing. Support for postfix operators
  will be removed altogether in the release of pg_builder that supports new syntax features of Postgres 14. 

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
[1.0.0-beta]: https://github.com/sad-spirit/pg-builder/compare/v0.4.1...v1.0.0-beta
[1.0.0]: https://github.com/sad-spirit/pg-builder/compare/v1.0.0-beta...v1.0.0
[1.0.1]: https://github.com/sad-spirit/pg-builder/compare/v1.0.0...v1.0.1
[1.0.2]: https://github.com/sad-spirit/pg-builder/compare/v1.0.1...v1.0.2
[2.0.0-beta]: https://github.com/sad-spirit/pg-builder/compare/v1.0.2...v2.0.0-beta
[2.0.0]: https://github.com/sad-spirit/pg-builder/compare/v2.0.0-beta...v2.0.0
[2.0.1]: https://github.com/sad-spirit/pg-builder/compare/v2.0.0...v2.0.1
[2.1.0-beta]: https://github.com/sad-spirit/pg-builder/compare/v2.0.1...v2.1.0-beta
[2.1.0-beta.2]: https://github.com/sad-spirit/pg-builder/compare/v2.1.0-beta...v2.1.0-beta.2
[2.1.0]: https://github.com/sad-spirit/pg-builder/compare/v2.1.0-beta.2...v2.1.0
[2.2.0]: https://github.com/sad-spirit/pg-builder/compare/v2.1.0...v2.2.0
[2.3.0-beta]: https://github.com/sad-spirit/pg-builder/compare/v2.2.0...v2.3.0-beta
[2.3.0]: https://github.com/sad-spirit/pg-builder/compare/v2.3.0-beta...v2.3.0
[2.3.1]: https://github.com/sad-spirit/pg-builder/compare/v2.3.0...v2.3.1
[2.4.0]: https://github.com/sad-spirit/pg-builder/compare/v2.3.1...v2.4.0
[Unreleased]: https://github.com/sad-spirit/pg-builder/compare/v2.4.0...HEAD