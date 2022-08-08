<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
     * @param Select $statement
     * @return mixed
     * @since 0.1.0
     */
    public function walkSelectStatement(Select $statement);

    /**
     * Visits the node representing a set operator applied to two select statements
     *
     * @param SetOpSelect $statement
     * @return mixed
     * @since 0.1.0
     */
    public function walkSetOpSelectStatement(SetOpSelect $statement);

    /**
     * Visits the node representing a complete VALUES statement
     *
     * @param Values $statement
     * @return mixed
     * @since 0.1.0
     */
    public function walkValuesStatement(Values $statement);

    /**
     * Visits the node representing a complete DELETE statement
     *
     * @param Delete $statement
     * @return mixed
     * @since 0.1.0
     */
    public function walkDeleteStatement(Delete $statement);

    /**
     * Visits the node representing a complete INSERT statement
     *
     * @param Insert $statement
     * @return mixed
     * @since 0.1.0
     */
    public function walkInsertStatement(Insert $statement);

    /**
     * Visits the node representing a complete UPDATE statement
     *
     * @param Update $statement
     * @return mixed
     * @since 0.1.0
     */
    public function walkUpdateStatement(Update $statement);


    /**
     * Visits the node representing array subscript [foo] or slice [1:bar] operation
     *
     * @param nodes\ArrayIndexes $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkArrayIndexes(nodes\ArrayIndexes $node);

    /**
     * Visits the node representing a (possibly qualified) column reference
     *
     * @param nodes\ColumnReference $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkColumnReference(nodes\ColumnReference $node);

    /**
     * Visits the node representing a CTE (i.e. part of WITH clause)
     *
     * @param nodes\CommonTableExpression $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkCommonTableExpression(nodes\CommonTableExpression $node);

    /**
     * Visits the node representing a constant that is an SQL keyword (true / false / null)
     *
     * @param nodes\expressions\KeywordConstant $node
     * @return mixed
     * @since 1.0.0 replaces old walkConstant() method
     */
    public function walkKeywordConstant(nodes\expressions\KeywordConstant $node);

    /**
     * Visits the node representing a numeric constant
     *
     * @param nodes\expressions\NumericConstant $node
     * @return mixed
     * @since 1.0.0 replaces old walkConstant() method
     */
    public function walkNumericConstant(nodes\expressions\NumericConstant $node);

    /**
     * Visits the node representing a string constant (including bit-strings)
     *
     * @param nodes\expressions\StringConstant $node
     * @return mixed
     * @since 1.0.0 replaces old walkConstant() method
     */
    public function walkStringConstant(nodes\expressions\StringConstant $node);

    /**
     * Visits the node representing a generic function call
     *
     * @param nodes\FunctionCall $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkFunctionCall(nodes\FunctionCall $node);

    /**
     * Visits the node representing a special parameterless function
     *
     * @param nodes\expressions\SQLValueFunction $node
     * @return mixed
     * @since 1.0.0
     */
    public function walkSQLValueFunction(nodes\expressions\SQLValueFunction $node);

    /**
     * Visits the node representing a system function
     *
     * @param nodes\expressions\SystemFunctionCall $node
     * @return mixed
     * @since 1.0.0 Previously represented by FunctionCall / FunctionExpression nodes
     */
    public function walkSystemFunctionCall(nodes\expressions\SystemFunctionCall $node);

    /**
     * Visits the node representing an identifier (e.g. column name or field name)
     *
     * @param nodes\Identifier $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkIdentifier(nodes\Identifier $node);

    /**
     * Visits the node representing indirection (field selections or array subscripts) applied to an expression
     *
     * @param nodes\Indirection $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkIndirection(nodes\Indirection $node);

    /**
     * Visits the node representing locking options in SELECT clause (FOR UPDATE ... and related clauses)
     *
     * @param nodes\LockingElement $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkLockingElement(nodes\LockingElement $node);

    /**
     * Visits the node representing an expression from ORDER BY clause
     *
     * @param nodes\OrderByElement $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkOrderByElement(nodes\OrderByElement $node);

    /**
     * Visits the node representing a named ':foo' query parameter
     *
     * @param nodes\expressions\NamedParameter $node
     * @return mixed
     * @since 1.0.0 replaces old walkParameter() method
     */
    public function walkNamedParameter(nodes\expressions\NamedParameter $node);

    /**
     * Visits the node representing a positional $1 query parameter
     *
     * @param nodes\expressions\PositionalParameter $node
     * @return mixed
     * @since 1.0.0 replaces old walkParameter() method
     */
    public function walkPositionalParameter(nodes\expressions\PositionalParameter $node);

    /**
     * Visits the node representing a (possibly qualified) name of a database object like relation, function or type
     *
     * @param nodes\QualifiedName $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkQualifiedName(nodes\QualifiedName $node);

    /**
     * Visits the node representing an OPERATOR(...) construct
     *
     * @param nodes\QualifiedOperator $node
     * @return mixed
     * @since 1.0.0
     */
    public function walkQualifiedOperator(nodes\QualifiedOperator $node);

    /**
     * Visits the node representing a target column (with possible indirection) for INSERT or UPDATE statements
     *
     * @param nodes\SetTargetElement $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkSetTargetElement(nodes\SetTargetElement $node);

    /**
     * Visits the node representing a single "column_name = expression" clause of UPDATE statement
     *
     * @param nodes\SingleSetClause $node
     * @return mixed
     * @since 0.2.0
     */
    public function walkSingleSetClause(nodes\SingleSetClause $node);

    /**
     * Visits the node representing a (column_name, ...) = (sub-select|row-expression) construct in SET clause of UPDATE
     *
     * @param nodes\MultipleSetClause $node
     * @return mixed
     * @since 0.2.0
     */
    public function walkMultipleSetClause(nodes\MultipleSetClause $node);

    /**
     * Visits the node representing the DEFAULT keyword in INSERT and UPDATE statements
     *
     * @param nodes\SetToDefault $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkSetToDefault(nodes\SetToDefault $node);

    /**
     * Visits the node representing a '*' meaning "all fields"
     *
     * @param nodes\Star $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkStar(nodes\Star $node);

    /**
     * Visits the node representing a part of target list for a statement (e.g. columns in SELECT)
     *
     * @param nodes\TargetElement $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkTargetElement(nodes\TargetElement $node);

    /**
     * Visits the node representing a type name with possible modifiers and array bounds
     *
     * @param nodes\TypeName $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkTypeName(nodes\TypeName $node);

    /**
     * Visits the wrapper node around ScalarExpression representing WHERE or HAVING conditions
     *
     * @param nodes\WhereOrHavingClause $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node);

    /**
     * Visits the node representing a window definition (for function calls with OVER and for WINDOW clause)
     *
     * @param nodes\WindowDefinition $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkWindowDefinition(nodes\WindowDefinition $node);

    /**
     * Visits the node representing a window frame (part of window definition)
     *
     * @param nodes\WindowFrameClause $node
     * @return mixed
     * @since 0.3.0 WindowFrameClause was extracted from WindowDefinition
     */
    public function walkWindowFrameClause(nodes\WindowFrameClause $node);

    /**
     * Visits the node representing a window frame bound (part of window frame)
     *
     * @param nodes\WindowFrameBound $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkWindowFrameBound(nodes\WindowFrameBound $node);

    /**
     * Visits the node representing a WITH clause
     *
     * @param nodes\WithClause $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkWithClause(nodes\WithClause $node);


    /**
     * Visits the node representing an array constructed from a list of values: ARRAY[...]
     *
     * @param nodes\expressions\ArrayExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkArrayExpression(nodes\expressions\ArrayExpression $expression);

    /**
     * Visits the node representing a keyword ANY / ALL / SOME applied to an array-type expression
     *
     * @param nodes\expressions\ArrayComparisonExpression $expression
     * @return mixed
     * since 1.0.0 Previously represented by FunctionCall / FunctionExpression nodes
     */
    public function walkArrayComparisonExpression(nodes\expressions\ArrayComparisonExpression $expression);

    /**
     * Visits the node representing "... AT TIME ZONE ..." expression
     *
     * @param nodes\expressions\AtTimeZoneExpression $expression
     * @return mixed
     * @since 1.0.0 "AT TIME ZONE" was previously represented by OperatorExpression
     */
    public function walkAtTimeZoneExpression(nodes\expressions\AtTimeZoneExpression $expression);

    /**
     * Visits the node representing "... BETWEEN ... AND ..." expression
     *
     * @param nodes\expressions\BetweenExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression);

    /**
     * Visits the node representing "CASE ... END" expression
     *
     * @param nodes\expressions\CaseExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkCaseExpression(nodes\expressions\CaseExpression $expression);

    /**
     * Visits the node representing "... COLLATE ..." expression
     *
     * @param nodes\expressions\CollateExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkCollateExpression(nodes\expressions\CollateExpression $expression);

    /**
     * Visits the node representing COLLATION FOR(...) expression
     *
     * @param nodes\expressions\CollationForExpression $expression
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkCollationForExpression(nodes\expressions\CollationForExpression $expression);

    /**
     * Visits the node representing EXTRACT(field FROM source) expression
     *
     * @param nodes\expressions\ExtractExpression $expression
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkExtractExpression(nodes\expressions\ExtractExpression $expression);

    /**
     * Visits the node representing a function call in scalar context
     *
     * @param nodes\expressions\FunctionExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkFunctionExpression(nodes\expressions\FunctionExpression $expression);

    /**
     * Visits the node representing "... [NOT] IN (...)" expression
     *
     * @param nodes\expressions\InExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkInExpression(nodes\expressions\InExpression $expression);

    /**
     * Visits the node representing a "... IS [NOT] DISTINCT FROM ..." expression
     *
     * @param nodes\expressions\IsDistinctFromExpression $expression
     * @return mixed
     * @since 1.0.0 "IS DISTINCT FROM" was previously represented by OperatorExpression
     */
    public function walkIsDistinctFromExpression(nodes\expressions\IsDistinctFromExpression $expression);

    /**
     * Visits the node representing "... IS [NOT] ..." expression
     *
     * @param nodes\expressions\IsExpression $expression
     * @return mixed
     * @since 1.0.0 "IS ..." was previously represented by OperatorExpression
     */
    public function walkIsExpression(nodes\expressions\IsExpression $expression);

    /**
     * Visits the node representing a group of expressions combined by AND or OR operators
     *
     * @param nodes\expressions\LogicalExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression);

    /**
     * Visits the node representing NORMALIZE(...) function call with special arguments format
     *
     * @param nodes\expressions\NormalizeExpression $expression
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkNormalizeExpression(nodes\expressions\NormalizeExpression $expression);

    /**
     * Visits the node representing a logical NOT operator applied to an expression
     *
     * @param nodes\expressions\NotExpression $expression
     * @return mixed
     * @since 1.0.0 "NOT ..." was previously represented by OperatorExpression
     */
    public function walkNotExpression(nodes\expressions\NotExpression $expression);

    /**
     * Visits the node representing a NULLIF(first, second) construct
     *
     * @param nodes\expressions\NullIfExpression $expression
     * @return mixed
     * @since 1.0.0 Previously represented by FunctionCall / FunctionExpression nodes
     */
    public function walkNullIfExpression(nodes\expressions\NullIfExpression $expression);

    /**
     * Visits the node representing a generic operator-like expression
     *
     * @param nodes\expressions\OperatorExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression);

    /**
     * Visits the node representing an "(...) OVERLAPS (...)" expression
     *
     * @param nodes\expressions\OverlapsExpression $expression
     * @return mixed
     * @since 1.0.0 "(...) OVERLAPS (...)" was previously represented by OperatorExpression
     */
    public function walkOverlapsExpression(nodes\expressions\OverlapsExpression $expression);

    /**
     * Visits the node representing OVERLAY(...) function call with special arguments format
     *
     * @param nodes\expressions\OverlayExpression $expression
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkOverlayExpression(nodes\expressions\OverlayExpression $expression);

    /**
     * Visits the node representing [NOT] LIKE | ILIKE | SIMILAR TO operators
     *
     * @param nodes\expressions\PatternMatchingExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression);

    /**
     * Visits the node representing POSITION(...) function call with special arguments format
     *
     * @param nodes\expressions\PositionExpression $expression
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkPositionExpression(nodes\expressions\PositionExpression $expression);

    /**
     * Visits the node representing a ROW(...) constructor expression
     *
     * @param nodes\expressions\RowExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkRowExpression(nodes\expressions\RowExpression $expression);

    /**
     * Visits the node representing a subquery in scalar expression, possibly with an operator applied
     *
     * @param nodes\expressions\SubselectExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkSubselectExpression(nodes\expressions\SubselectExpression $expression);

    /**
     * Visits the node representing SUBSTRING(string FROM ...) function call with special arguments format
     *
     * @param nodes\expressions\SubstringFromExpression $expression
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkSubstringFromExpression(nodes\expressions\SubstringFromExpression $expression);

    /**
     * Visits the node representing SUBSTRING(string SIMILAR ...) function call with special arguments format
     *
     * @return mixed
     * @since 2.0.0
     */
    public function walkSubstringSimilarExpression(nodes\expressions\SubstringSimilarExpression $expression);

    /**
     * Visits the node representing TRIM(...) function call with special arguments format
     *
     * @param nodes\expressions\TrimExpression $expression
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkTrimExpression(nodes\expressions\TrimExpression $expression);

    /**
     * Visits the node representing a conversion of some value to a given datatype
     *
     * @param nodes\expressions\TypecastExpression $expression
     * @return mixed
     * @since 0.1.0
     */
    public function walkTypecastExpression(nodes\expressions\TypecastExpression $expression);

    /**
     * Visits the node representing a "type.name 'a string value'" type cast
     *
     * @param nodes\expressions\ConstantTypecastExpression $expression
     * @return mixed
     * @since 2.0.0
     */
    public function walkConstantTypecastExpression(nodes\expressions\ConstantTypecastExpression $expression);

    /**
     * Visits the node representing a GROUPING(...) expression
     *
     * @param nodes\expressions\GroupingExpression $expression
     * @return mixed
     * @since 0.2.0
     */
    public function walkGroupingExpression(nodes\expressions\GroupingExpression $expression);

    /**
     * Most of the lists do not have any additional features and may be handled by a generic method
     *
     * @template T of Node
     * @template TListInput
     * @param NodeList<int, T, TListInput> $list
     * @return mixed
     * @since 0.1.0
     */
    public function walkGenericNodeList(NodeList $list);

    /**
     * Visits the node representing a list of function arguments
     *
     * @param nodes\lists\FunctionArgumentList $list
     * @return mixed
     * @since 0.1.0
     */
    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list);


    /**
     * Visits the node representing a column definition (can be used as column aliases for functions in FROM clause)
     *
     * @param nodes\range\ColumnDefinition $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkColumnDefinition(nodes\range\ColumnDefinition $node);

    /**
     * Visits the node representing a function call in FROM clause
     *
     * @param nodes\range\FunctionCall $rangeItem
     * @return mixed
     * @since 0.1.0
     */
    public function walkRangeFunctionCall(nodes\range\FunctionCall $rangeItem);

    /**
     * Visits the node representing a JOIN expression in FROM clause
     *
     * @param nodes\range\JoinExpression $rangeItem
     * @return mixed
     * @since 0.1.0
     */
    public function walkJoinExpression(nodes\range\JoinExpression $rangeItem);

    /**
     * Visits the node representing a relation (table or view) reference in FROM clause
     *
     * @param nodes\range\RelationReference $rangeItem
     * @return mixed
     * @since 0.1.0
     */
    public function walkRelationReference(nodes\range\RelationReference $rangeItem);

    /**
     * Visits the node representing a ROWS FROM() construct in FROM clause
     *
     * @param nodes\range\RowsFrom $rangeItem
     * @return mixed
     * @since 0.1.0
     */
    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem);

    /**
     * Visits the node representing a function call inside ROWS FROM construct
     *
     * @param nodes\range\RowsFromElement $node
     * @return mixed
     * @since 0.1.0
     */
    public function walkRowsFromElement(nodes\range\RowsFromElement $node);

    /**
     * Visits the node representing a subselect in FROM clause
     *
     * @param nodes\range\Subselect $rangeItem
     * @return mixed
     * @since 0.1.0
     */
    public function walkRangeSubselect(nodes\range\Subselect $rangeItem);

    /**
     * Visits the node representing a target of INSERT statement (relation name with possible alias)
     *
     * @param nodes\range\InsertTarget $target
     * @return mixed
     * @since 0.2.0
     */
    public function walkInsertTarget(nodes\range\InsertTarget $target);

    /**
     * Visits the node representing a target of UPDATE or DELETE statement
     *
     * @param nodes\range\UpdateOrDeleteTarget $target
     * @return mixed
     * @since 0.2.0
     */
    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target);

    /**
     * Visits the node representing a TABLESAMPLE clause in FROM list
     *
     * @param nodes\range\TableSample $rangeItem
     * @return mixed
     * @since 0.2.0
     */
    public function walkTableSample(nodes\range\TableSample $rangeItem);


    /**
     * Visits the node representing an xmlelement() expression
     *
     * @param nodes\xml\XmlElement $xml
     * @return mixed
     * @since 0.1.0
     */
    public function walkXmlElement(nodes\xml\XmlElement $xml);

    /**
     * Visits the node representing XMLEXISTS() expression
     *
     * @param nodes\xml\XmlExists $xml
     * @return mixed
     * @since 2.0.0 previously represented by FunctionExpression
     */
    public function walkXmlExists(nodes\xml\XmlExists $xml);

    /**
     * Visits the node representing an xmlforest() expression
     *
     * @param nodes\xml\XmlForest $xml
     * @return mixed
     * @since 0.1.0
     */
    public function walkXmlForest(nodes\xml\XmlForest $xml);

    /**
     * Visits the node representing an xmlparse() expression
     *
     * @param nodes\xml\XmlParse $xml
     * @return mixed
     * @since 0.1.0
     */
    public function walkXmlParse(nodes\xml\XmlParse $xml);

    /**
     * Visits the node representing an xmlpi() expression
     *
     * @param nodes\xml\XmlPi $xml
     * @return mixed
     * @since 0.1.0
     */
    public function walkXmlPi(nodes\xml\XmlPi $xml);

    /**
     * Visits the node representing an xmlroot() expression
     *
     * @param nodes\xml\XmlRoot $xml
     * @return mixed
     * @since 0.1.0
     */
    public function walkXmlRoot(nodes\xml\XmlRoot $xml);

    /**
     * Visits the node representing an xmlserialize() expression
     *
     * @param nodes\xml\XmlSerialize $xml
     * @return mixed
     * @since 0.1.0
     */
    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml);

    /**
     * Visits the node representing an XMLTABLE clause in FROM
     *
     * @param nodes\range\XmlTable $table
     * @return mixed
     * @since 0.2.0
     */
    public function walkXmlTable(nodes\range\XmlTable $table);

    /**
     * Visits the node representing a column definition in XMLTABLE clause
     *
     * @param nodes\xml\XmlTypedColumnDefinition $column
     * @return mixed
     * @since 1.0.0 replaces old walkXmlColumnDefinition() method
     */
    public function walkXmlTypedColumnDefinition(nodes\xml\XmlTypedColumnDefinition $column);

    /**
     * Visits the node representing a column definition in XMLTABLE clause
     *
     * @param nodes\xml\XmlOrdinalityColumnDefinition $column
     * @return mixed
     * @since 1.0.0 replaces old walkXmlColumnDefinition() method
     */
    public function walkXmlOrdinalityColumnDefinition(nodes\xml\XmlOrdinalityColumnDefinition $column);

    /**
     * Visits the node representing an XML namespace in XMLTABLE clause
     *
     * @param nodes\xml\XmlNamespace $ns
     * @return mixed
     * @since 0.2.0
     */
    public function walkXmlNamespace(nodes\xml\XmlNamespace $ns);


    /**
     * Visits the node representing ON CONFLICT clause of INSERT statement
     *
     * @param nodes\OnConflictClause $onConflict
     * @return mixed
     * @since 0.2.0
     */
    public function walkOnConflictClause(nodes\OnConflictClause $onConflict);

    /**
     * Visits the node representing index parameters from ON CONFLICT clause
     *
     * @param nodes\IndexParameters $parameters
     * @return mixed
     * @since 0.2.0
     */
    public function walkIndexParameters(nodes\IndexParameters $parameters);

    /**
     * Visits the node representing a column description in CREATE INDEX statement (actually used in ON CONFLICT clause)
     *
     * @param nodes\IndexElement $element
     * @return mixed
     * @since 0.2.0
     */
    public function walkIndexElement(nodes\IndexElement $element);


    /**
     * Visits the node representing an empty grouping set '()' in GROUP BY clause
     *
     * @param nodes\group\EmptyGroupingSet $empty
     * @return mixed
     * @since 0.2.0
     */
    public function walkEmptyGroupingSet(nodes\group\EmptyGroupingSet $empty);

    /**
     * Visits the node representing CUBE(...) or ROLLUP(...) construct in GROUP BY clause
     *
     * @param nodes\group\CubeOrRollupClause $clause
     * @return mixed
     * @since 0.2.0
     */
    public function walkCubeOrRollupClause(nodes\group\CubeOrRollupClause $clause);

    /**
     * Visits the node representing GROUPING SETS(...) construct in GROUP BY clause
     *
     * @param nodes\group\GroupingSetsClause $clause
     * @return mixed
     * @since 0.2.0
     */
    public function walkGroupingSetsClause(nodes\group\GroupingSetsClause $clause);

    /**
     * Visits the node representing GROUP BY clause
     *
     * @param nodes\group\GroupByClause $clause
     * @return mixed
     * @since 2.0.0
     */
    public function walkGroupByClause(nodes\group\GroupByClause $clause);

    /**
     * Visits the node representing SEARCH BREADTH / DEPTH FIRST clause for Common Table Expressions
     *
     * @param nodes\cte\SearchClause $clause
     * @return mixed
     * @since 2.0.0
     */
    public function walkSearchClause(nodes\cte\SearchClause $clause);

    /**
     * Visits the node representing CYCLE clause for Common Table Expressions
     *
     * @param nodes\cte\CycleClause $clause
     * @return mixed
     * @since 2.0.0
     */
    public function walkCycleClause(nodes\cte\CycleClause $clause);

    /**
     * Visits the node representing USING clause of JOIN expression
     *
     * @param nodes\range\UsingClause $clause
     * @return mixed
     * @since 2.0.0
     */
    public function walkUsingClause(nodes\range\UsingClause $clause);

    /**
     * Visits the node representing "foo ... IS [NOT] JSON ..." expression
     *
     * @param nodes\expressions\IsJsonExpression $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkIsJsonExpression(nodes\expressions\IsJsonExpression $expression);

    /**
     * Visits the node representing FORMAT clause of JSON expressions
     *
     * @param nodes\json\JsonFormat $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonFormat(nodes\json\JsonFormat $clause);

    /**
     * Visits the node representing RETURNING clause of JSON expressions
     *
     * @param nodes\json\JsonReturning $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonReturning(nodes\json\JsonReturning $clause);

    /**
     * Visits the node representing JSON value (an expression with possible trailing FORMAT)
     *
     * @param nodes\json\JsonFormattedValue $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonFormattedValue(nodes\json\JsonFormattedValue $clause);

    /**
     * Visits the node representing a JSON key-value pair
     *
     * @param nodes\json\JsonKeyValue $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonKeyValue(nodes\json\JsonKeyValue $clause);

    /**
     * Visits the node representing json_arrayagg() expression
     *
     * @param nodes\json\JsonArrayAgg $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonArrayAgg(nodes\json\JsonArrayAgg $expression);

    /**
     * Visits the node representing json_objectagg() expression
     *
     * @param nodes\json\JsonObjectAgg $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonObjectAgg(nodes\json\JsonObjectAgg $expression);

    /**
     * Visits the node representing json_array() expression
     *
     * @param nodes\json\JsonArray $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonArray(nodes\json\JsonArray $expression);

    /**
     * Visits the node representing json_object() expression
     *
     * @param nodes\json\JsonObject $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonObject(nodes\json\JsonObject $expression);

    /**
     * Visits the node representing json() expression
     *
     * @param nodes\json\JsonConstructor $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonConstructor(nodes\json\JsonConstructor $expression);

    /**
     * Visits the node representing json_scalar() expression
     *
     * @param nodes\json\JsonScalar $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonScalar(nodes\json\JsonScalar $expression);

    /**
     * Visits the node representing json_serialize() expression
     *
     * @param nodes\json\JsonSerialize $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonSerialize(nodes\json\JsonSerialize $expression);

    /**
     * Visits the node representing an element of JSON "PASSING ..." clause
     *
     * @param nodes\json\JsonArgument $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonArgument(nodes\json\JsonArgument $clause);

    /**
     * Visits the node representing json_exists() expression
     *
     * @param nodes\json\JsonExists $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonExists(nodes\json\JsonExists $expression);

    /**
     * Visits the node representing json_value() expression
     *
     * @param nodes\json\JsonValue $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonValue(nodes\json\JsonValue $expression);

    /**
     * Visits the node representing json_query() expression
     *
     * @param nodes\json\JsonQuery $expression
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonQuery(nodes\json\JsonQuery $expression);

    /**
     * Visits the node representing json_table() expression
     *
     * @param nodes\range\JsonTable $rangeItem
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonTable(nodes\range\JsonTable $rangeItem);

    /**
     * Visits the node representing an EXISTS column definition in json_table() expression
     *
     * @param nodes\range\json\JsonExistsColumnDefinition $column
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonExistsColumnDefinition(nodes\range\json\JsonExistsColumnDefinition $column);

    /**
     * Visits the node representing column definition with FORMAT clause in json_table() expression
     *
     * @param nodes\range\json\JsonFormattedColumnDefinition $column
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonFormattedColumnDefinition(nodes\range\json\JsonFormattedColumnDefinition $column);

    /**
     * Visits the node representing a "FOR ORDINALITY" column definition in json_table() expression
     *
     * @param nodes\range\json\JsonOrdinalityColumnDefinition $column
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonOrdinalityColumnDefinition(nodes\range\json\JsonOrdinalityColumnDefinition $column);

    /**
     * Visits the node representing a regular column definition in json_table() expression
     *
     * @param nodes\range\json\JsonRegularColumnDefinition $column
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonRegularColumnDefinition(nodes\range\json\JsonRegularColumnDefinition $column);

    /**
     * Visits the node representing nested column definitions in json_table() expression
     *
     * @param nodes\range\json\JsonNestedColumns $column
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonNestedColumns(nodes\range\json\JsonNestedColumns $column);

    /**
     * Visits the node representing "PLAN DEFAULT(...)" clause in json_table() expression
     *
     * @param nodes\range\json\JsonTableDefaultPlan $plan
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonTableDefaultPlan(nodes\range\json\JsonTableDefaultPlan $plan);

    /**
     * Visits the node representing part of "PLAN(...)" clause in json_table() expression that specifies parent-child
     * joining plan
     *
     * @param nodes\range\json\JsonTableParentChildPlan $plan
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonTableParentChildPlan(nodes\range\json\JsonTableParentChildPlan $plan);

    /**
     * Visits the node representing part of "PLAN(...)" clause in json_table() expression that specifies sibling
     * joining plan
     *
     * @param nodes\range\json\JsonTableSiblingPlan $plan
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonTableSiblingPlan(nodes\range\json\JsonTableSiblingPlan $plan);

    /**
     * Visits the node representing a reference to a named path within "PLAN(...)" clause of json_table()
     *
     * @param nodes\range\json\JsonTableSimplePlan $plan
     * @return mixed
     * @since 2.1.0
     */
    public function walkJsonTableSimplePlan(nodes\range\json\JsonTableSimplePlan $plan);

    /**
     * Visits the node representing a complete MERGE statement
     *
     * @param Merge $statement
     * @return mixed
     * @since 2.1.0
     */
    public function walkMergeStatement(Merge $statement);

    /**
     * Visits the node representing the DELETE action of MERGE statement
     *
     * @param nodes\merge\MergeDelete $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkMergeDelete(nodes\merge\MergeDelete $clause);

    /**
     * Visits the node representing the INSERT action of MERGE statement
     *
     * @param nodes\merge\MergeInsert $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkMergeInsert(nodes\merge\MergeInsert $clause);

    /**
     * Visits the node representing the UPDATE action of MERGE statement
     *
     * @param nodes\merge\MergeUpdate $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkMergeUpdate(nodes\merge\MergeUpdate $clause);

    /**
     * Visits the node representing VALUES part of INSERT action for MERGE statement
     *
     * @param nodes\merge\MergeValues $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkMergeValues(nodes\merge\MergeValues $clause);

    /**
     * Visits the node representing "WHEN MATCHED ..." clause of MERGE statement
     *
     * @param nodes\merge\MergeWhenMatched $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkMergeWhenMatched(nodes\merge\MergeWhenMatched $clause);

    /**
     * Visits the node representing "WHEN NOT MATCHED ..." clause of MERGE statement
     *
     * @param nodes\merge\MergeWhenNotMatched $clause
     * @return mixed
     * @since 2.1.0
     */
    public function walkMergeWhenNotMatched(nodes\merge\MergeWhenNotMatched $clause);
}
