# Upgrading from 2.x to 3.0

The main user-facing issues are
 * Classes supporting integration with `sad_spirit\pg_wrapper` had deprecated features removed;
 * Public properties of several `Node` implementations changed type from `string` to enums;
 * Additional typehints, especially for `TreeWalker`.

The changes to classes used in `Lexer` and `Parser` are documented for completeness.

## Removed features
 * Features deprecated in 2.x releases were removed
   * `converters\ParserAwareTypeConverterFactory` class, `converters\BuilderSupportDecorator` should be used instead.
     Before:
     ```PHP
     $factory = new ParserAwareTypeConverterFactory($parser);
     ```
     now:
     ```PHP
     $factory = new BuilderSupportDecorator($connection->getTypeConverterFactory(), $parser);
     ```
   * `$resultTypes` parameter for `NativeStatement::executePrepared()`, the types should be
     passed to `NativeStatement::prepare()` (they are _unlikely_ to change after `prepare()`).
     Before:
     ```PHP
     $statement->prepare($connection, $paramTypes);
     $result = $statement->executePrepared($params, $resultTypes);
     ```
     now:
     ```PHP
     $statement->prepare($connection, $paramTypes, $resultTypes);
     $result = $statement->executePrepared($params);
     ```
 * `nodes\GenericNode` no longer implements deprecated `Serializable` interface, `serialize()` and `unserialize()`
   methods were removed from it and its subclasses.
 * `$format` property and constructor argument for `nodes\json\JsonFormat` as it is currently hardcoded to `json`.
 * `Keywords` class. Replaced by `Keyword` enum, see below.

## Changed `Parser`-related features

### `Token` class -> interface

`Token` class was converted to an interface with several implementations:
 * Abstract `tokens\GenericToken`
   * `tokens\EOFToken` signalling end of input;
   * `tokens\KeywordToken` representing a keyword;
   * `tokens\StringToken` having a type and a string value - basically anything that is not a keyword.

`Token::TYPE_` constants are now represented by cases of `TokenType` enum, `Token::typeToString()` is replaced by
`TokenType::toString`.

Before:
```PHP
$identifier = new Token(Token::TYPE_IDENTIFIER, 'foo', 123);
$keyword    = new Token(Token::TYPE_RESERVED_KEYWORD, 'default', 456);
```
now:
```PHP
$identifier = new StringToken(TokenType::IDENTIFIER, 'foo', 123);
$keyword    = new KeywordToken(Keyword::DEFAULT, 456);
```

### `Keywords` class -> `Keyword` enum

Postgres keywords are now represented as cases of `Keyword` enum rather than as string literals. Features that were
previously available in `Keywords` class are now in this enum.
 * Checking whether the given string represents a keyword, before:
   ```PHP
   if (Keywords::isKeyword('select')) {
       // process keyword
   }
   ```
   now (using standard backed enum method):
   ```PHP
   if (null !== Keyword::tryFrom('select')) {
       // process keyword
   }
   ```
 * Getting the type of the keyword, before:
   ```PHP
   $type = Keywords::LIST['select'][0]; // Token::TYPE_RESERVED_KEYWORD
   ```
   now:
   ```PHP
   $type = Keyword::SELECT->getType(); // TokenType::RESERVED_KEYWORD
   ```
 * Checking whether the keyword can be used as a column alias without `AS`, before:
   ```PHP
   Keywords::isBareLabelKeyword('and');
   ```
   now:
   ```PHP
   Keyword::AND->isBareLabel();
   ```

## Conversion of string properties to enums

All the enums mentioned below are string-backed. The backing values for all of them (with the notable
exception of `enums\StringConstantType`) are either single Postgres keywords or several keywords separated by
spaces, e.g.
```PHP
enum IntervalMask: string
{
    case YEAR  = 'year';
    // ...
    case YTM   = 'year to month';
    // ...
}
```
These backing values correspond to the strings that were accepted by `Node` classes and the case names correspond to
constants that were previously defined in these classes, so migration is simple enough. If using string literals,
before:
```PHP
$node = new SQLValueFunction('current_schema');
```
now:
```PHP
$node = new SQLValueFunction(SQLValueFunctionName::from('current_schema'))
```
If using constants, before:
```PHP
$node = new SQLValueFunction(SQLValueFunction::CURRENT_DATE);
```
now:
```PHP
$node = new SQLValueFunction(SQLValueFunctionName::CURRENT_DATE);
```



The following properties/arguments were converted:

 * `Insert`: `$overriding` property is `enums\InsertOverriding`.
 * `SetOpSelect`: `$operator` property and constructor argument is `enums\SetOperator`.
 * `nodes\IndexElement`: `$direction` property and `$direction` argument for constructor
   is `enums\IndexElementDirection`; `$nullsOrder` property and constructor
   argument is `enums\NullsOrder`.
 * `nodes\IntervalTypeName`: `$mask` property is `enums\IntervalMask`.
 * `nodes\LockingElement`: `$strength` property and constructor argument is `enums\LockingStrength`.
 * `nodes\OnConflictClause`: `$action` property and constructor argument is `enums\OnConflictAction`.
 * `nodes\OrderByElement`: `$direction` property and `$direction` argument for constructor
   is `enums\OrderByDirection`; `$nullsOrder` property and constructor argument is `enums\NullsOrder`.
 * `nodes\WindowFrameBound`: `$direction` property and constructor argument is `enums\WindowFrameDirection`.
 * `nodes\WindowFrameClause`: `$exclusion` property and constructor argument is `enums\WindowFrameExclusion`;
   `$type` property and constructor argument is `enums\WindowFrameMode`.
 * `nodes\expressions\ArrayComparisonExpression`: `$keyword` property and `$keyword` constructor
   argument is now a case of `enums\ArrayComparisonConstruct`.
 * `nodes\expressions\BetweenExpression`: `$operator` property and `$operator` constructor argument
   is `enums\BetweenPredicate`.
 * `nodes\expressions\ExtractExpression`: `$field` property and `$field` constructor argument
   can be either a string or `enums\ExtractPart` (the latter standing for a known keyword).
 * `nodes\expressions\IsExpression`: `$what` property and constructor argument is `enums\IsPredicate`.
 * `nodes\expressions\IsJsonExpression`: `$type` property is `enums\IsJsonType`.
 * `nodes\expressions\KeywordConstant`: `$value` argument for constructor is `enums\ConstantName`.
   NB: `$value` property remains string!
 * `nodes\expressions\LogicalExpression`: `$operator` property and constructor argument is `enums\LogicalOperator`.
 * `nodes\expressions\NormalizeExpression`: `$form` property and constructor argument is `enums\NormalizeForm`.
 * `nodes\expressions\PatternMatchingExpression`: `$operator` property and constructor argument is
   `enums\PatternPredicate`.
 * `nodes\expressions\SQLValueFunction`: `$name` property and constructor argument is `enums\SQLValueFunctionName`.
 * `nodes\expressions\StringConstant`: `$type` property and constructor argument is `enums\StringConstantType`.
 * `nodes\expressions\SubselectExpression`: `$operator` property and constructor argument is `enums\SubselectConstruct`.
 * `nodes\expressions\SystemFunctionCall`: `$name` property and constructor argument is `enums\SystemFunctionName`.
 * `nodes\expressions\TrimExpression`: `$side` property and constructor argument is `enums\TrimSide`.
 * `nodes\group\CubeOrRollupClause`: `$type` property and `$type` constructor
   argument is `enums\CubeOrRollup`.
 * `nodes\json\JsonFormat`: `$encoding` property and constructor argument is `enums\JsonEncoding`.
 * `nodes\merge\MergeInsert`: `$overriding` property and constructor argument is `enums\InsertOverriding`.
 * `nodes\range\JoinExpression`: `$type` property and constructor argument is `enums\JoinType`.
 * `nodes\xml\XmlParse`: `$documentOrContent` property and constructor argument is `enums\XmlOption`.
 * `nodes\xml\XmlSerialize`: `$documentOrContent` property and constructor argument is `enums\XmlOption`.
 * `nodes\xml\XmlRoot`: `$standalone` property and constructor argument is `enums\XmlStandalone`.

Additionally
 * `nodes\range\FromElement`: `$joinType` argument to `join()` is now `enums\JoinType`
