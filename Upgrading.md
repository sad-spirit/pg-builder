# Upgrading from 2.x to 3.0

The main user-facing issues are
 * Classes supporting integration with `sad_spirit\pg_wrapper` had deprecated features removed;
 * Public properties of several `Node` implementations changed type from `string` to enums;
 * Removed default values for setter method arguments; 
 * Additional typehints, especially for `TreeWalker`.

The changes to classes used in `Lexer` and `Parser` are documented for completeness but are unlikely to cause issues.

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
constants that were previously defined in these classes, so migration is simple enough. Using string literals,
before:
```PHP
$node = new SQLValueFunction('current_schema');
```
now:
```PHP
$node = new SQLValueFunction(SQLValueFunctionName::from('current_schema'))
```
Using constants, before:
```PHP
$node = new SQLValueFunction(SQLValueFunction::CURRENT_DATE);
```
now:
```PHP
$node = new SQLValueFunction(SQLValueFunctionName::CURRENT_DATE);
```

When reading an enum-backed property, its `$value` property should be used if string representation is needed. Before:
```PHP
// prints e.g. 'exists'
echo $subselect->operator;
```
now:
```PHP
echo $subselect->operator->value;
```



The following properties/arguments were converted:

 * `Insert`: `$overriding` property is now a case of `enums\InsertOverriding`.
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

## Removed default values for arguments of setter methods

Implicitly nullable parameters were [deprecated in PHP 8.4](https://www.php.net/manual/en/migration84.deprecated.php),
thus argument typehints were changed to explicitly nullable and their default `null` values were removed,
```PHP
$bar->setFoo();
```
looks weird unlike more explicit
```PHP
$bar->setFoo(null);
```
Some of the setter methods had defaults other than `null`, these were also removed.

Note that it was never recommended to directly call the below methods, except `Node::setParentNode()`:
they are currently used to support writable magic properties, but that implementation detail may change.
```PHP
// Calling setter method, not recommended
$select->setLimit('10')
// Setting the property is the recommended way
$select->limit = '10';
```


Methods that changed signatures:

 * `Node` (and all its implementations)
   * `public function setParentNode(Node $parent = null): void` -> `public function setParentNode(?Node $parent): void`
 * `Insert`:
   * `public function setValues(SelectCommon $values = null): void` -> `public function setValues(?SelectCommon $values): void`
   * `public function setOnConflict($onConflict = null): void` -> `public function setOnConflict(string|OnConflictClause|null $onConflict): void`
   * `public function setOverriding(?string $overriding = null): void` -> `public function setOverriding(?InsertOverriding $overriding): void`
 * `SelectCommon`
   * `public function setLimit($limit = null): void` -> `public function setLimit(string|ScalarExpression|null $limit): void`
   * `public function setOffset($offset = null): void` -> `public function setOffset(string|ScalarExpression|null $offset): void`
 * `nodes\ArrayIndexes`
   * `public function setLower(ScalarExpression $lower = null): void` -> `public function setLower(?ScalarExpression $lower): void`
   * `public function setUpper(ScalarExpression $upper = null): void` -> `public function setUpper(?ScalarExpression $upper): void`
 * `nodes\IntervalTypeName`
   * `public function setMask(string $mask = ''): void` -> `public function setMask(?IntervalMask $mask): void`
 * `nodes\OnConflictClause`
   * `public function setTarget(Node $target = null): void` -> `public function setTarget(IndexParameters|Identifier|null $target): void`
 * `nodes\TypeName`
   * `public function setSetOf(bool $setOf = false): void` -> `public function setSetOf(bool $setOf): void`
 * `nodes\WhereOrHavingClause`
   * `public function setCondition($condition = null): self` -> `public function setCondition(string|null|self|ScalarExpression $condition): self`
 * `nodes\WindowDefinition`
   * `public function setName(Identifier $name = null): void` -> `public function setName(?Identifier $name): void`
 * `nodes\WindowFrameBound`
   * `public function setValue(ScalarExpression $value = null): void` -> `public function setValue(?ScalarExpression $value): void`
 * `nodes\expressions\CaseExpression`
   * `public function setArgument(ScalarExpression $argument = null): void` -> `public function setArgument(?ScalarExpression $argument): void`
   * `public function setElse(ScalarExpression $elseClause = null): void` -> `public function setElse(?ScalarExpression $elseClause): void`
 * `nodes\expressions\OperatorExpression`
   * `public function setLeft(ScalarExpression $left = null): void` -> `public function setLeft(?ScalarExpression $left): void`
 * `nodes\expressions\PatternMatchingExpression`
   * `public function setEscape(ScalarExpression $escape = null): void` -> `public function setEscape(?ScalarExpression $escape): void`
 * `nodes\merge\MergeInsert`
   * `public function setOverriding(?string $overriding = null): void` -> `public function setOverriding(?InsertOverriding $overriding): void`
 * `nodes\range\FromElement` (and subclasses)
   * `public function setAlias(Identifier $tableAlias = null, NodeList $columnAliases = null): void`
     -> `public function setAlias(?Identifier $tableAlias, ?NodeList $columnAliases = null): void`
 * `nodes\range\JoinExpression`
   * `public function setUsing($using = null): void` -> `public function setUsing(null|string|iterable|UsingClause $using): void`
   * `public function setOn($on = null): void` -> `public function setOn(null|string|ScalarExpression $on): void`
 * `nodes\range\TableSample`
   * `public function setRepeatable(ScalarExpression $repeatable = null): void` -> `public function setRepeatable(?ScalarExpression $repeatable): void`
 * `nodes\xml\XmlNamespace`
   * `public function setAlias(Identifier $alias = null): void` -> `public function setAlias(?Identifier $alias): void`
 * `nodes\xml\XmlRoot`
   * `public function setVersion(ScalarExpression $version = null): void` -> `public function setVersion(?ScalarExpression $version): void`
 * `nodes\xml\XmlTypedColumnDefinition`
   * `public function setPath(ScalarExpression $path = null): void` -> `public function setPath(?ScalarExpression $path): void`
   * `public function setNullable(?bool $nullable = null): void` -> `public function setNullable(?bool $nullable): void`
   * `public function setDefault(ScalarExpression $default = null): void` -> `public function setDefault(?ScalarExpression $default): void`

## Changed `Parser`-related features

### `Token` class -> interface

`Token` class was converted to an interface with several implementations:
* Abstract `tokens\GenericToken`
   * `tokens\EOFToken` signalling end of input;
   * `tokens\KeywordToken` representing a keyword;
   * `tokens\StringToken` having a type and a string value - basically anything that is not a keyword.

`Token::TYPE_` constants are now represented by cases of `TokenType` enum, `Token::typeToString()` is replaced by
`TokenType::toString()`.

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
