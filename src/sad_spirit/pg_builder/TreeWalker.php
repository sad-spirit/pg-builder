<?php
/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

/**
 * Interface for walkers of abstract syntax trees
 *
 */
interface TreeWalker
{
    public function walkSelectStatement(Select $statement);

    public function walkSetOpSelectStatement(SetOpSelect $statement);

    public function walkValuesStatement(Values $statement);

    public function walkDeleteStatement(Delete $statement);

    public function walkInsertStatement(Insert $statement);

    public function walkUpdateStatement(Update $statement);


    public function walkArrayIndexes(nodes\ArrayIndexes $node);

    public function walkColumnReference(nodes\ColumnReference $node);

    public function walkCommonTableExpression(nodes\CommonTableExpression $node);

    public function walkConstant(nodes\Constant $node);

    public function walkFunctionCall(nodes\FunctionCall $node);

    public function walkIdentifier(nodes\Identifier $node);

    public function walkIndirection(nodes\Indirection $node);

    public function walkLockingElement(nodes\LockingElement $node);

    public function walkOrderByElement(nodes\OrderByElement $node);

    public function walkParameter(nodes\Parameter $node);

    public function walkQualifiedName(nodes\QualifiedName $node);

    public function walkSetTargetElement(nodes\SetTargetElement $node);

    public function walkSetToDefault(nodes\SetToDefault $node);

    public function walkStar(nodes\Star $node);

    public function walkTargetElement(nodes\TargetElement $node);

    public function walkTypeName(nodes\TypeName $node);

    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node);

    public function walkWindowDefinition(nodes\WindowDefinition $node);

    public function walkWindowFrameBound(nodes\WindowFrameBound $node);

    public function walkWithClause(nodes\WithClause $node);


    public function walkArrayExpression(nodes\expressions\ArrayExpression $expression);

    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression);

    public function walkCaseExpression(nodes\expressions\CaseExpression $expression);

    public function walkCollateExpression(nodes\expressions\CollateExpression $expression);

    public function walkFunctionExpression(nodes\expressions\FunctionExpression $expression);

    public function walkInExpression(nodes\expressions\InExpression $expression);

    public function walkIsOfExpression(nodes\expressions\IsOfExpression $expression);

    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression);

    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression);

    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression);

    public function walkRowExpression(nodes\expressions\RowExpression $expression);

    public function walkSubselectExpression(nodes\expressions\SubselectExpression $expression);

    public function walkTypecastExpression(nodes\expressions\TypecastExpression $expression);


    /**
     * Most of the lists do not have any additional features and may be handled by a generic method
     *
     * @param NodeList $list
     * @return mixed
     */
    public function walkGenericNodeList(NodeList $list);

    public function walkCtextRow(nodes\lists\CtextRow $list);

    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list);


    public function walkColumnDefinition(nodes\range\ColumnDefinition $node);

    public function walkRangeFunctionCall(nodes\range\FunctionCall $rangeItem);

    public function walkJoinExpression(nodes\range\JoinExpression $rangeItem);

    public function walkRelationReference(nodes\range\RelationReference $rangeItem);

    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem);

    public function walkRowsFromElement(nodes\range\RowsFromElement $node);

    public function walkRangeSubselect(nodes\range\Subselect $rangeItem);

    public function walkInsertTarget(nodes\range\InsertTarget $target);

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target);


    public function walkXmlElement(nodes\xml\XmlElement $xml);

    public function walkXmlForest(nodes\xml\XmlForest $xml);

    public function walkXmlParse(nodes\xml\XmlParse $xml);

    public function walkXmlPi(nodes\xml\XmlPi $xml);

    public function walkXmlRoot(nodes\xml\XmlRoot $xml);

    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml);
}