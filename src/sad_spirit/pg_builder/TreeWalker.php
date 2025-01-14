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

namespace sad_spirit\pg_builder;

/**
 * Interface for walkers of abstract syntax trees
 *
 */
interface TreeWalker
{
    /**
     * Visits the node representing a complete SELECT statement
     *
     * @since 0.1.0
     */
    public function walkSelectStatement(Select $statement): mixed;

    /**
     * Visits the node representing a set operator applied to two select statements
     *
     * @since 0.1.0
     */
    public function walkSetOpSelectStatement(SetOpSelect $statement): mixed;

    /**
     * Visits the node representing a complete VALUES statement
     *
     * @since 0.1.0
     */
    public function walkValuesStatement(Values $statement): mixed;

    /**
     * Visits the node representing a complete DELETE statement
     *
     * @since 0.1.0
     */
    public function walkDeleteStatement(Delete $statement): mixed;

    /**
     * Visits the node representing a complete INSERT statement
     *
     * @since 0.1.0
     */
    public function walkInsertStatement(Insert $statement): mixed;

    /**
     * Visits the node representing a complete UPDATE statement
     *
     * @since 0.1.0
     */
    public function walkUpdateStatement(Update $statement): mixed;


    /**
     * Visits the node representing array subscript [foo] or slice [1:bar] operation
     *
     * @since 0.1.0
     */
    public function walkArrayIndexes(nodes\ArrayIndexes $node): mixed;

    /**
     * Visits the node representing a (possibly qualified) column reference
     *
     * @since 0.1.0
     */
    public function walkColumnReference(nodes\ColumnReference $node): mixed;

    /**
     * Visits the node representing a CTE (i.e. part of WITH clause)
     *
     * @since 0.1.0
     */
    public function walkCommonTableExpression(nodes\CommonTableExpression $node): mixed;

    /**
     * Visits the node representing a constant that is an SQL keyword (true / false / null)
     *
     * @since 1.0.0 replaces old walkConstant() method
     */
    public function walkKeywordConstant(nodes\expressions\KeywordConstant $node): mixed;

    /**
     * Visits the node representing a numeric constant
     *
     * @since 1.0.0 replaces old walkConstant() method
     */
    public function walkNumericConstant(nodes\expressions\NumericConstant $node): mixed;

    /**
     * Visits the node representing a string constant (including bit-strings)
     *
     * @since 1.0.0 replaces old walkConstant() method
     */
    public function walkStringConstant(nodes\expressions\StringConstant $node): mixed;

    /**
     * Visits the node representing a generic function call
     *
     * @since 0.1.0
     */
    public function walkFunctionCall(nodes\FunctionCall $node): mixed;

    /**
     * Visits the node representing a special parameterless function
     *
     * @since 1.0.0
     */
    public function walkSQLValueFunction(nodes\expressions\SQLValueFunction $node): mixed;

    /**
     * Visits the node representing a system function
     *
     * @since 1.0.0 Previously represented by FunctionCall / FunctionExpression nodes
     */
    public function walkSystemFunctionCall(nodes\expressions\SystemFunctionCall $node): mixed;

    /**
     * Visits the node representing an identifier (e.g. column name or field name)
     *
     * @since 0.1.0
     */
    public function walkIdentifier(nodes\Identifier $node): mixed;

    /**
     * Visits the node representing indirection (field selections or array subscripts) applied to an expression
     *
     * @since 0.1.0
     */
    public function walkIndirection(nodes\Indirection $node): mixed;

    /**
     * Visits the node representing locking options in SELECT clause (FOR UPDATE ... and related clauses)
     *
     * @since 0.1.0
     */
    public function walkLockingElement(nodes\LockingElement $node): mixed;

    /**
     * Visits the node representing an expression from ORDER BY clause
     *
     * @since 0.1.0
     */
    public function walkOrderByElement(nodes\OrderByElement $node): mixed;

    /**
     * Visits the node representing a named ':foo' query parameter
     *
     * @since 1.0.0 replaces old walkParameter() method
     */
    public function walkNamedParameter(nodes\expressions\NamedParameter $node): mixed;

    /**
     * Visits the node representing a positional $1 query parameter
     *
     * @since 1.0.0 replaces old walkParameter() method
     */
    public function walkPositionalParameter(nodes\expressions\PositionalParameter $node): mixed;

    /**
     * Visits the node representing a (possibly qualified) name of a database object like relation, function or type
     *
     * @since 0.1.0
     */
    public function walkQualifiedName(nodes\QualifiedName $node): mixed;

    /**
     * Visits the node representing an OPERATOR(...) construct
     *
     * @since 1.0.0
     */
    public function walkQualifiedOperator(nodes\QualifiedOperator $node): mixed;

    /**
     * Visits the node representing a target column (with possible indirection) for INSERT or UPDATE statements
     *
     * @since 0.1.0
     */
    public function walkSetTargetElement(nodes\SetTargetElement $node): mixed;

    /**
     * Visits the node representing a single "column_name = expression" clause of UPDATE statement
     *
     * @since 0.2.0
     */
    public function walkSingleSetClause(nodes\SingleSetClause $node): mixed;

    /**
     * Visits the node representing a (column_name, ...) = (sub-select|row-expression) construct in SET clause of UPDATE
     *
     * @since 0.2.0
     */
    public function walkMultipleSetClause(nodes\MultipleSetClause $node): mixed;

    /**
     * Visits the node representing the DEFAULT keyword in INSERT and UPDATE statements
     *
     * @since 0.1.0
     */
    public function walkSetToDefault(nodes\SetToDefault $node): mixed;

    /**
     * Visits the node representing a '*' meaning "all fields"
     *
     * @since 0.1.0
     */
    public function walkStar(nodes\Star $node): mixed;

    /**
     * Visits the node representing a part of target list for a statement (e.g. columns in SELECT)
     *
     * @since 0.1.0
     */
    public function walkTargetElement(nodes\TargetElement $node): mixed;

    /**
     * Visits the node representing a type name with possible modifiers and array bounds
     *
     * @since 0.1.0
     */
    public function walkTypeName(nodes\TypeName $node): mixed;

    /**
     * Visits the wrapper node around ScalarExpression representing WHERE or HAVING conditions
     *
     * @since 0.1.0
     */
    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node): mixed;

    /**
     * Visits the node representing a window definition (for function calls with OVER and for WINDOW clause)
     *
     * @since 0.1.0
     */
    public function walkWindowDefinition(nodes\WindowDefinition $node): mixed;

    /**
     * Visits the node representing a window frame (part of window definition)
     *
     * @since 0.3.0 WindowFrameClause was extracted from WindowDefinition
     */
    public function walkWindowFrameClause(nodes\WindowFrameClause $node): mixed;

    /**
     * Visits the node representing a window frame bound (part of window frame)
     *
     * @since 0.1.0
     */
    public function walkWindowFrameBound(nodes\WindowFrameBound $node): mixed;

    /**
     * Visits the node representing a WITH clause
     *
     * @since 0.1.0
     */
    public function walkWithClause(nodes\WithClause $node): mixed;


    /**
     * Visits the node representing an array constructed from a list of values: ARRAY[...]
     *
     * @since 0.1.0
     */
    public function walkArrayExpression(nodes\expressions\ArrayExpression $expression): mixed;

    /**
     * Visits the node representing a keyword ANY / ALL / SOME applied to an array-type expression
     *
     * @param nodes\expressions\ArrayComparisonExpression $expression
     * since 1.0.0 Previously represented by FunctionCall / FunctionExpression nodes
     */
    public function walkArrayComparisonExpression(nodes\expressions\ArrayComparisonExpression $expression): mixed;

    /**
     * Visits the node representing "... AT TIME ZONE ..." expression
     *
     * @since 1.0.0 "AT TIME ZONE" was previously represented by OperatorExpression
     */
    public function walkAtTimeZoneExpression(nodes\expressions\AtTimeZoneExpression $expression): mixed;

    /**
     * Visits the node representin "... AT LOCAL" expression
     *
     * Syntax added in PostgreSQL 17
     *
     * @since 3.0.0
     */
    public function walkAtLocalExpression(nodes\expressions\AtLocalExpression $expression): mixed;

    /**
     * Visits the node representing "... BETWEEN ... AND ..." expression
     *
     * @since 0.1.0
     */
    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression): mixed;

    /**
     * Visits the node representing "CASE ... END" expression
     *
     * @since 0.1.0
     */
    public function walkCaseExpression(nodes\expressions\CaseExpression $expression): mixed;

    /**
     * Visits the node representing "... COLLATE ..." expression
     *
     * @since 0.1.0
     */
    public function walkCollateExpression(nodes\expressions\CollateExpression $expression): mixed;

    /**
     * Visits the node representing COLLATION FOR(...) expression
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkCollationForExpression(nodes\expressions\CollationForExpression $expression): mixed;

    /**
     * Visits the node representing EXTRACT(field FROM source) expression
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkExtractExpression(nodes\expressions\ExtractExpression $expression): mixed;

    /**
     * Visits the node representing a function call in scalar context
     *
     * @since 0.1.0
     */
    public function walkFunctionExpression(nodes\expressions\FunctionExpression $expression): mixed;

    /**
     * Visits the node representing "... [NOT] IN (...)" expression
     *
     * @since 0.1.0
     */
    public function walkInExpression(nodes\expressions\InExpression $expression): mixed;

    /**
     * Visits the node representing a "... IS [NOT] DISTINCT FROM ..." expression
     *
     * @since 1.0.0 "IS DISTINCT FROM" was previously represented by OperatorExpression
     */
    public function walkIsDistinctFromExpression(nodes\expressions\IsDistinctFromExpression $expression): mixed;

    /**
     * Visits the node representing "... IS [NOT] ..." expression
     *
     * @since 1.0.0 "IS ..." was previously represented by OperatorExpression
     */
    public function walkIsExpression(nodes\expressions\IsExpression $expression): mixed;

    /**
     * Visits the node representing a group of expressions combined by `AND` or `OR` operators
     *
     * @since 0.1.0
     */
    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression): mixed;

    /**
     * Visits the node representing NORMALIZE(...) function call with special arguments format
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkNormalizeExpression(nodes\expressions\NormalizeExpression $expression): mixed;

    /**
     * Visits the node representing a logical NOT operator applied to an expression
     *
     * @since 1.0.0 "NOT ..." was previously represented by OperatorExpression
     */
    public function walkNotExpression(nodes\expressions\NotExpression $expression): mixed;

    /**
     * Visits the node representing a NULLIF(first, second) construct
     *
     * @since 1.0.0 Previously represented by FunctionCall / FunctionExpression nodes
     */
    public function walkNullIfExpression(nodes\expressions\NullIfExpression $expression): mixed;

    /**
     * Visits the node representing a generic operator-like expression
     *
     * @since 0.1.0
     */
    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression): mixed;

    /**
     * Visits the node representing an "(...) OVERLAPS (...)" expression
     *
     * @since 1.0.0 "(...) OVERLAPS (...)" was previously represented by OperatorExpression
     */
    public function walkOverlapsExpression(nodes\expressions\OverlapsExpression $expression): mixed;

    /**
     * Visits the node representing OVERLAY(...) function call with special arguments format
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkOverlayExpression(nodes\expressions\OverlayExpression $expression): mixed;

    /**
     * Visits the node representing [NOT] LIKE | ILIKE | SIMILAR TO operators
     *
     * @since 0.1.0
     */
    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression): mixed;

    /**
     * Visits the node representing POSITION(...) function call with special arguments format
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkPositionExpression(nodes\expressions\PositionExpression $expression): mixed;

    /**
     * Visits the node representing a ROW(...) constructor expression
     *
     * @since 0.1.0
     */
    public function walkRowExpression(nodes\expressions\RowExpression $expression): mixed;

    /**
     * Visits the node representing a subquery in scalar expression, possibly with an operator applied
     *
     * @since 0.1.0
     */
    public function walkSubselectExpression(nodes\expressions\SubselectExpression $expression): mixed;

    /**
     * Visits the node representing SUBSTRING(string FROM ...) function call with special arguments format
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkSubstringFromExpression(nodes\expressions\SubstringFromExpression $expression): mixed;

    /**
     * Visits the node representing SUBSTRING(string SIMILAR ...) function call with special arguments format
     *
     * @since 2.0.0
     */
    public function walkSubstringSimilarExpression(nodes\expressions\SubstringSimilarExpression $expression): mixed;

    /**
     * Visits the node representing TRIM(...) function call with special arguments format
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkTrimExpression(nodes\expressions\TrimExpression $expression): mixed;

    /**
     * Visits the node representing a conversion of some value to a given datatype
     *
     * @since 0.1.0
     */
    public function walkTypecastExpression(nodes\expressions\TypecastExpression $expression): mixed;

    /**
     * Visits the node representing a "type.name 'a string value'" type cast
     *
     * @since 2.0.0
     */
    public function walkConstantTypecastExpression(nodes\expressions\ConstantTypecastExpression $expression): mixed;

    /**
     * Visits the node representing a GROUPING(...) expression
     *
     * @since 0.2.0
     */
    public function walkGroupingExpression(nodes\expressions\GroupingExpression $expression): mixed;

    /**
     * Most of the lists do not have any additional features and may be handled by a generic method
     *
     * @param \Traversable<Node> $list
     * @since 0.1.0
     */
    public function walkGenericNodeList(\Traversable $list): mixed;

    /**
     * Visits the node representing a list of function arguments
     *
     * @since 0.1.0
     */
    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list): mixed;


    /**
     * Visits the node representing a column definition (can be used as column aliases for functions in FROM clause)
     *
     * @since 0.1.0
     */
    public function walkColumnDefinition(nodes\range\ColumnDefinition $node): mixed;

    /**
     * Visits the node representing a function call in FROM clause
     *
     * @since 0.1.0
     */
    public function walkRangeFunctionCall(nodes\range\FunctionCall $rangeItem): mixed;

    /**
     * Visits the node representing a JOIN expression in FROM clause
     *
     * @since 0.1.0
     */
    public function walkJoinExpression(nodes\range\JoinExpression $rangeItem): mixed;

    /**
     * Visits the node representing a relation (table or view) reference in FROM clause
     *
     * @since 0.1.0
     */
    public function walkRelationReference(nodes\range\RelationReference $rangeItem): mixed;

    /**
     * Visits the node representing a ROWS FROM() construct in FROM clause
     *
     * @since 0.1.0
     */
    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem): mixed;

    /**
     * Visits the node representing a function call inside ROWS FROM construct
     *
     * @since 0.1.0
     */
    public function walkRowsFromElement(nodes\range\RowsFromElement $node): mixed;

    /**
     * Visits the node representing a subselect in FROM clause
     *
     * @since 0.1.0
     */
    public function walkRangeSubselect(nodes\range\Subselect $rangeItem): mixed;

    /**
     * Visits the node representing a target of INSERT statement (relation name with possible alias)
     *
     * @since 0.2.0
     */
    public function walkInsertTarget(nodes\range\InsertTarget $target): mixed;

    /**
     * Visits the node representing a target of UPDATE or DELETE statement
     *
     * @since 0.2.0
     */
    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target): mixed;

    /**
     * Visits the node representing a TABLESAMPLE clause in FROM list
     *
     * @since 0.2.0
     */
    public function walkTableSample(nodes\range\TableSample $rangeItem): mixed;


    /**
     * Visits the node representing an xmlelement() expression
     *
     * @since 0.1.0
     */
    public function walkXmlElement(nodes\xml\XmlElement $xml): mixed;

    /**
     * Visits the node representing XMLEXISTS() expression
     *
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkXmlExists(nodes\xml\XmlExists $xml): mixed;

    /**
     * Visits the node representing a xmlforest() expression
     *
     * @since 0.1.0
     */
    public function walkXmlForest(nodes\xml\XmlForest $xml): mixed;

    /**
     * Visits the node representing a xmlparse() expression
     *
     * @since 0.1.0
     */
    public function walkXmlParse(nodes\xml\XmlParse $xml): mixed;

    /**
     * Visits the node representing a xmlpi() expression
     *
     * @since 0.1.0
     */
    public function walkXmlPi(nodes\xml\XmlPi $xml): mixed;

    /**
     * Visits the node representing a xmlroot() expression
     *
     * @since 0.1.0
     */
    public function walkXmlRoot(nodes\xml\XmlRoot $xml): mixed;

    /**
     * Visits the node representing a xmlserialize() expression
     *
     * @since 0.1.0
     */
    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml): mixed;

    /**
     * Visits the node representing an `XMLTABLE` clause in FROM
     *
     * @since 0.2.0
     */
    public function walkXmlTable(nodes\range\XmlTable $table): mixed;

    /**
     * Visits the node representing a column definition in XMLTABLE clause
     *
     * @since 1.0.0 replaces old walkXmlColumnDefinition() method
     */
    public function walkXmlTypedColumnDefinition(nodes\xml\XmlTypedColumnDefinition $column): mixed;

    /**
     * Visits the node representing a column definition in XMLTABLE clause
     *
     * @since 1.0.0 replaces old walkXmlColumnDefinition() method
     */
    public function walkXmlOrdinalityColumnDefinition(nodes\xml\XmlOrdinalityColumnDefinition $column): mixed;

    /**
     * Visits the node representing an XML namespace in XMLTABLE clause
     *
     * @since 0.2.0
     */
    public function walkXmlNamespace(nodes\xml\XmlNamespace $ns): mixed;


    /**
     * Visits the node representing ON CONFLICT clause of INSERT statement
     *
     * @since 0.2.0
     */
    public function walkOnConflictClause(nodes\OnConflictClause $onConflict): mixed;

    /**
     * Visits the node representing index parameters from ON CONFLICT clause
     *
     * @since 0.2.0
     */
    public function walkIndexParameters(nodes\IndexParameters $parameters): mixed;

    /**
     * Visits the node representing a column description in CREATE INDEX statement (actually used in ON CONFLICT clause)
     *
     * @since 0.2.0
     */
    public function walkIndexElement(nodes\IndexElement $element): mixed;


    /**
     * Visits the node representing an empty grouping set '()' in GROUP BY clause
     *
     * @since 0.2.0
     */
    public function walkEmptyGroupingSet(nodes\group\EmptyGroupingSet $empty): mixed;

    /**
     * Visits the node representing CUBE(...) or ROLLUP(...) construct in GROUP BY clause
     *
     * @since 0.2.0
     */
    public function walkCubeOrRollupClause(nodes\group\CubeOrRollupClause $clause): mixed;

    /**
     * Visits the node representing GROUPING SETS(...) construct in GROUP BY clause
     *
     * @since 0.2.0
     */
    public function walkGroupingSetsClause(nodes\group\GroupingSetsClause $clause): mixed;

    /**
     * Visits the node representing GROUP BY clause
     *
     * @since 2.0.0
     */
    public function walkGroupByClause(nodes\group\GroupByClause $clause): mixed;

    /**
     * Visits the node representing SEARCH BREADTH / DEPTH FIRST clause for Common Table Expressions
     *
     * @since 2.0.0
     */
    public function walkSearchClause(nodes\cte\SearchClause $clause): mixed;

    /**
     * Visits the node representing CYCLE clause for Common Table Expressions
     *
     * @since 2.0.0
     */
    public function walkCycleClause(nodes\cte\CycleClause $clause): mixed;

    /**
     * Visits the node representing USING clause of JOIN expression
     *
     * @since 2.0.0
     */
    public function walkUsingClause(nodes\range\UsingClause $clause): mixed;

    /**
     * Visits the node representing a complete MERGE statement
     *
     * @since 2.1.0
     */
    public function walkMergeStatement(Merge $statement): mixed;

    /**
     * Visits the node representing the DELETE action of MERGE statement
     *
     * @since 2.1.0
     */
    public function walkMergeDelete(nodes\merge\MergeDelete $clause): mixed;

    /**
     * Visits the node representing the INSERT action of MERGE statement
     *
     * @since 2.1.0
     */
    public function walkMergeInsert(nodes\merge\MergeInsert $clause): mixed;

    /**
     * Visits the node representing the UPDATE action of MERGE statement
     *
     * @since 2.1.0
     */
    public function walkMergeUpdate(nodes\merge\MergeUpdate $clause): mixed;

    /**
     * Visits the node representing VALUES part of INSERT action for MERGE statement
     *
     * @since 2.1.0
     */
    public function walkMergeValues(nodes\merge\MergeValues $clause): mixed;

    /**
     * Visits the node representing "WHEN MATCHED ..." clause of MERGE statement
     *
     * @since 2.1.0
     */
    public function walkMergeWhenMatched(nodes\merge\MergeWhenMatched $clause): mixed;

    /**
     * Visits the node representing "WHEN NOT MATCHED ..." clause of MERGE statement
     *
     * @since 2.1.0
     */
    public function walkMergeWhenNotMatched(nodes\merge\MergeWhenNotMatched $clause): mixed;

    /**
     * Visits the node representing `merge_action()` construct
     *
     * @since 3.0.0
     */
    public function walkMergeAction(nodes\expressions\MergeAction $action): mixed;

    /**
     * Visits the node representing "foo ... IS [NOT] JSON ..." expression
     *
     * @since 2.3.0
     */
    public function walkIsJsonExpression(nodes\expressions\IsJsonExpression $expression): mixed;

    /**
     * Visits the node representing FORMAT clause of JSON expressions
     *
     * @since 2.3.0
     */
    public function walkJsonFormat(nodes\json\JsonFormat $clause): mixed;

    /**
     * Visits the node representing RETURNING clause of JSON expressions
     *
     * @since 2.3.0
     */
    public function walkJsonReturning(nodes\json\JsonReturning $clause): mixed;

    /**
     * Visits the node representing JSON value (an expression with possible trailing FORMAT)
     *
     * @since 2.3.0
     */
    public function walkJsonFormattedValue(nodes\json\JsonFormattedValue $clause): mixed;

    /**
     * Visits the node representing a JSON key-value pair
     *
     * @since 2.3.0
     */
    public function walkJsonKeyValue(nodes\json\JsonKeyValue $clause): mixed;

    /**
     * Visits the node representing json_arrayagg() expression
     *
     * @since 2.3.0
     */
    public function walkJsonArrayAgg(nodes\json\JsonArrayAgg $expression): mixed;

    /**
     * Visits the node representing json_objectagg() expression
     *
     * @since 2.3.0
     */
    public function walkJsonObjectAgg(nodes\json\JsonObjectAgg $expression): mixed;

    /**
     * Visits the node representing json_array() expression with a list of expressions as argument
     *
     * @since 2.3.0
     */
    public function walkJsonArrayValueList(nodes\json\JsonArrayValueList $expression): mixed;

    /**
     * Visits the node representing json_array() expression with a subselect as argument
     *
     * @since 2.3.0
     */
    public function walkJsonArraySubselect(nodes\json\JsonArraySubselect $expression): mixed;

    /**
     * Visits the node representing json_object() expression
     *
     * @since 2.3.0
     */
    public function walkJsonObject(nodes\json\JsonObject $expression): mixed;


    /**
     * Visits the node representing json() expression
     *
     * @since 3.0.0
     */
    public function walkJsonConstructor(nodes\json\JsonConstructor $expression): mixed;

    /**
     * Visits the node representing json_scalar() expression
     *
     * @since 3.0.0
     */
    public function walkJsonScalar(nodes\json\JsonScalar $expression): mixed;

    /**
     * Visits the node representing json_serialize() expression
     *
     * @since 3.0.0
     */
    public function walkJsonSerialize(nodes\json\JsonSerialize $expression): mixed;

    /**
     * Visits the node representing an element of JSON "PASSING ..." clause
     *
     * @since 3.0.0
     */
    public function walkJsonArgument(nodes\json\JsonArgument $clause): mixed;

    /**
     * Visits the node representing json_exists() expression
     *
     * @since 3.0.0
     */
    public function walkJsonExists(nodes\json\JsonExists $expression): mixed;

    /**
     * Visits the node representing json_value() expression
     *
     * @since 3.0.0
     */
    public function walkJsonValue(nodes\json\JsonValue $expression): mixed;

    /**
     * Visits the node representing json_query() expression
     *
     * @since 3.0.0
     */
    public function walkJsonQuery(nodes\json\JsonQuery $expression): mixed;

    /**
     * Visits the node representing json_table() expression
     *
     * @since 3.0.0
     */
    public function walkJsonTable(nodes\range\JsonTable $rangeItem): mixed;

    /**
     * Visits the node representing an EXISTS column definition in json_table() expression
     *
     * @since 3.0.0
     */
    public function walkJsonExistsColumnDefinition(nodes\range\json\JsonExistsColumnDefinition $column): mixed;

    /**
     * Visits the node representing a "FOR ORDINALITY" column definition in json_table() expression
     *
     * @since 3.0.0
     */
    public function walkJsonOrdinalityColumnDefinition(nodes\range\json\JsonOrdinalityColumnDefinition $column): mixed;

    /**
     * Visits the node representing a regular column definition in json_table() expression
     *
     * @since 3.0.0
     */
    public function walkJsonRegularColumnDefinition(nodes\range\json\JsonRegularColumnDefinition $column): mixed;

    /**
     * Visits the node representing nested column definitions in json_table() expression
     *
     * @since 3.0.0
     */
    public function walkJsonNestedColumns(nodes\range\json\JsonNestedColumns $column): mixed;
}
