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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

/**
 * A convenience class for creating TreeWalker implementations
 *
 * It is recommended to extend this class rather than to directly implement TreeWalker
 * interface, main reason being that new methods can be added to TreeWalker once new
 * syntax of Postgres is supported.
 *
 * Methods in this class only do dispatch to child nodes, they do not perform any
 * other operations. If you need to visit all nodes of specific type you will
 * only need to re-implement walkSpecificNodeType() method in subclass of BlankWalker.
 */
abstract class BlankWalker implements TreeWalker
{
    protected function walkCommonSelectClauses(SelectCommon $statement): void
    {
        $statement->order->dispatch($this);
        $statement->locking->dispatch($this);
        if (null !== $statement->limit) {
            $statement->limit->dispatch($this);
        }
        if (null !== $statement->offset) {
            $statement->offset->dispatch($this);
        }
    }

    public function walkSelectStatement(Select $statement)
    {
        $statement->with->dispatch($this);
        $statement->list->dispatch($this);
        if ($statement->distinct instanceof Node) {
            $statement->distinct->dispatch($this);
        }
        $statement->from->dispatch($this);
        $statement->where->dispatch($this);
        $statement->group->dispatch($this);
        $statement->having->dispatch($this);
        $statement->window->dispatch($this);

        $this->walkCommonSelectClauses($statement);
        return null;
    }

    public function walkSetOpSelectStatement(SetOpSelect $statement)
    {
        $statement->with->dispatch($this);
        $statement->left->dispatch($this);
        $statement->right->dispatch($this);

        $this->walkCommonSelectClauses($statement);
        return null;
    }

    public function walkValuesStatement(Values $statement)
    {
        $statement->rows->dispatch($this);

        $this->walkCommonSelectClauses($statement);
        return null;
    }

    public function walkDeleteStatement(Delete $statement)
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->using->dispatch($this);
        $statement->where->dispatch($this);
        $statement->returning->dispatch($this);
        return null;
    }

    public function walkInsertStatement(Insert $statement)
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->cols->dispatch($this);
        if (null !== $statement->values) {
            $statement->values->dispatch($this);
        }
        if (null !== $statement->onConflict) {
            $statement->onConflict->dispatch($this);
        }
        $statement->returning->dispatch($this);
        return null;
    }

    public function walkUpdateStatement(Update $statement)
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->set->dispatch($this);
        $statement->from->dispatch($this);
        $statement->where->dispatch($this);
        $statement->returning->dispatch($this);
        return null;
    }


    public function walkArrayIndexes(nodes\ArrayIndexes $node)
    {
        if (null !== $node->lower) {
            $node->lower->dispatch($this);
        }
        if (null !== $node->upper) {
            $node->upper->dispatch($this);
        }
        return null;
    }

    public function walkColumnReference(nodes\ColumnReference $node)
    {
        if (null !== $node->catalog) {
            $node->catalog->dispatch($this);
        }
        if (null !== $node->schema) {
            $node->schema->dispatch($this);
        }
        if (null !== $node->relation) {
            $node->relation->dispatch($this);
        }
        $node->column->dispatch($this);
        return null;
    }

    public function walkCommonTableExpression(nodes\CommonTableExpression $node)
    {
        $node->statement->dispatch($this);
        $node->alias->dispatch($this);
        $node->columnAliases->dispatch($this);
        if (null !== $node->search) {
            $node->search->dispatch($this);
        }
        if (null !== $node->cycle) {
            $node->cycle->dispatch($this);
        }
        return null;
    }

    public function walkKeywordConstant(nodes\expressions\KeywordConstant $node)
    {
        return null;
    }

    public function walkNumericConstant(nodes\expressions\NumericConstant $node)
    {
        return null;
    }

    public function walkStringConstant(nodes\expressions\StringConstant $node)
    {
        return null;
    }

    public function walkFunctionCall(nodes\FunctionCall $node)
    {
        $node->name->dispatch($this);
        $node->arguments->dispatch($this);
        $node->order->dispatch($this);
        return null;
    }

    public function walkSQLValueFunction(nodes\expressions\SQLValueFunction $node)
    {
        if (null !== $node->modifier) {
            $node->modifier->dispatch($this);
        }
        return null;
    }

    public function walkSystemFunctionCall(nodes\expressions\SystemFunctionCall $node)
    {
        $node->arguments->dispatch($this);
        return null;
    }

    public function walkIdentifier(nodes\Identifier $node)
    {
        return null;
    }

    public function walkIndirection(nodes\Indirection $node)
    {
        $node->expression->dispatch($this);
        $this->walkGenericNodeList($node);
        return null;
    }

    public function walkLockingElement(nodes\LockingElement $node)
    {
        $this->walkGenericNodeList($node);
        return null;
    }

    public function walkOrderByElement(nodes\OrderByElement $node)
    {
        $node->expression->dispatch($this);
        if ($node->operator instanceof nodes\QualifiedOperator) {
            $node->operator->dispatch($this);
        }
        return null;
    }

    public function walkNamedParameter(nodes\expressions\NamedParameter $node)
    {
        return null;
    }

    public function walkPositionalParameter(nodes\expressions\PositionalParameter $node)
    {
        return null;
    }

    public function walkQualifiedName(nodes\QualifiedName $node)
    {
        if (null !== $node->catalog) {
            $node->catalog->dispatch($this);
        }
        if (null !== $node->schema) {
            $node->schema->dispatch($this);
        }
        $node->relation->dispatch($this);
        return null;
    }

    public function walkQualifiedOperator(nodes\QualifiedOperator $node)
    {
        if (null !== $node->catalog) {
            $node->catalog->dispatch($this);
        }
        if (null !== $node->schema) {
            $node->schema->dispatch($this);
        }
        return null;
    }


    public function walkSetTargetElement(nodes\SetTargetElement $node)
    {
        $node->name->dispatch($this);
        /** @var Node $item */
        foreach ($node as $item) {
            $item->dispatch($this);
        }
        return null;
    }

    public function walkSingleSetClause(nodes\SingleSetClause $node)
    {
        $node->column->dispatch($this);
        $node->value->dispatch($this);
        return null;
    }

    public function walkMultipleSetClause(nodes\MultipleSetClause $node)
    {
        $node->columns->dispatch($this);
        $node->value->dispatch($this);
        return null;
    }

    public function walkSetToDefault(nodes\SetToDefault $node)
    {
        return null;
    }

    public function walkStar(nodes\Star $node)
    {
        return null;
    }

    public function walkTargetElement(nodes\TargetElement $node)
    {
        $node->expression->dispatch($this);
        if (null !== $node->alias) {
            $node->alias->dispatch($this);
        }
        return null;
    }

    public function walkTypeName(nodes\TypeName $node)
    {
        if (!($node instanceof nodes\IntervalTypeName)) {
            $node->name->dispatch($this);
        }
        $node->modifiers->dispatch($this);
        return null;
    }

    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node)
    {
        if (null !== $node->condition) {
            $node->condition->dispatch($this);
        }
        return null;
    }

    public function walkWindowDefinition(nodes\WindowDefinition $node)
    {
        if (null !== $node->name) {
            $node->name->dispatch($this);
        }
        if (null !== $node->refName) {
            $node->refName->dispatch($this);
        }
        $node->partition->dispatch($this);
        $node->order->dispatch($this);
        if (null !== $node->frame) {
            $node->frame->dispatch($this);
        }
        return null;
    }

    public function walkWindowFrameClause(nodes\WindowFrameClause $node)
    {
        $node->start->dispatch($this);
        if (null !== $node->end) {
            $node->end->dispatch($this);
        }
        return null;
    }

    public function walkWindowFrameBound(nodes\WindowFrameBound $node)
    {
        if (null !== $node->value) {
            $node->value->dispatch($this);
        }
        return null;
    }

    public function walkWithClause(nodes\WithClause $node)
    {
        $this->walkGenericNodeList($node);
        return null;
    }

    public function walkArrayExpression(nodes\expressions\ArrayExpression $expression)
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkArrayComparisonExpression(nodes\expressions\ArrayComparisonExpression $expression)
    {
        $expression->array->dispatch($this);
        return null;
    }

    public function walkAtTimeZoneExpression(nodes\expressions\AtTimeZoneExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->timeZone->dispatch($this);
        return null;
    }

    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkCaseExpression(nodes\expressions\CaseExpression $expression)
    {
        if (null !== $expression->argument) {
            $expression->argument->dispatch($this);
        }
        /* @var nodes\expressions\WhenExpression $whenClause */
        foreach ($expression as $whenClause) {
            $whenClause->when->dispatch($this);
            $whenClause->then->dispatch($this);
        }
        if (null !== $expression->else) {
            $expression->else->dispatch($this);
        }
        return null;
    }

    public function walkCollateExpression(nodes\expressions\CollateExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->collation->dispatch($this);
        return null;
    }

    public function walkCollationForExpression(nodes\expressions\CollationForExpression $expression)
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkExtractExpression(nodes\expressions\ExtractExpression $expression)
    {
        $expression->source->dispatch($this);
        return null;
    }

    public function walkFunctionExpression(nodes\expressions\FunctionExpression $expression)
    {
        $this->walkFunctionCall($expression);
        if (null !== $expression->filter) {
            $expression->filter->dispatch($this);
        }
        if (null !== $expression->over) {
            $expression->over->dispatch($this);
        }
        return null;
    }

    public function walkInExpression(nodes\expressions\InExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkIsDistinctFromExpression(nodes\expressions\IsDistinctFromExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkIsExpression(nodes\expressions\IsExpression $expression)
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression)
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkNormalizeExpression(nodes\expressions\NormalizeExpression $expression)
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkNotExpression(nodes\expressions\NotExpression $expression)
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkNullIfExpression(nodes\expressions\NullIfExpression $expression)
    {
        $expression->first->dispatch($this);
        $expression->second->dispatch($this);
        return null;
    }

    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression)
    {
        if ($expression->operator instanceof nodes\QualifiedOperator) {
            $expression->operator->dispatch($this);
        }
        if (null !== $expression->left) {
            $expression->left->dispatch($this);
        }
        $expression->right->dispatch($this);
        return null;
    }

    public function walkOverlapsExpression(nodes\expressions\OverlapsExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkOverlayExpression(nodes\expressions\OverlayExpression $expression)
    {
        $expression->string->dispatch($this);
        $expression->newSubstring->dispatch($this);
        $expression->start->dispatch($this);
        if (null !== $expression->count) {
            $expression->count->dispatch($this);
        }
        return null;
    }

    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->pattern->dispatch($this);
        if (null !== $expression->escape) {
            $expression->escape->dispatch($this);
        }
        return null;
    }

    public function walkPositionExpression(nodes\expressions\PositionExpression $expression)
    {
        $expression->substring->dispatch($this);
        $expression->string->dispatch($this);
        return null;
    }

    public function walkRowExpression(nodes\expressions\RowExpression $expression)
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkSubselectExpression(nodes\expressions\SubselectExpression $expression)
    {
        $expression->query->dispatch($this);
        return null;
    }

    public function walkSubstringFromExpression(nodes\expressions\SubstringFromExpression $expression)
    {
        $expression->string->dispatch($this);
        if (null !== $expression->from) {
            $expression->from->dispatch($this);
        }
        if (null !== $expression->for) {
            $expression->for->dispatch($this);
        }
        return null;
    }

    public function walkSubstringSimilarExpression(nodes\expressions\SubstringSimilarExpression $expression)
    {
        $expression->string->dispatch($this);
        $expression->pattern->dispatch($this);
        $expression->escape->dispatch($this);
        return null;
    }

    public function walkTrimExpression(nodes\expressions\TrimExpression $expression)
    {
        $expression->arguments->dispatch($this);
        return null;
    }

    public function walkTypecastExpression(nodes\expressions\TypecastExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->type->dispatch($this);
        return null;
    }

    public function walkConstantTypecastExpression(nodes\expressions\ConstantTypecastExpression $expression)
    {
        return $this->walkTypecastExpression($expression);
    }

    public function walkGroupingExpression(nodes\expressions\GroupingExpression $expression)
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkGenericNodeList(NodeList $list)
    {
        /** @var Node $item */
        foreach ($list as $item) {
            $item->dispatch($this);
        }
        return null;
    }

    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list)
    {
        /** @var Node $item */
        foreach ($list as $item) {
            $item->dispatch($this);
        }
        return null;
    }


    public function walkColumnDefinition(nodes\range\ColumnDefinition $node)
    {
        $node->name->dispatch($this);
        $node->type->dispatch($this);
        if (null !== $node->collation) {
            $node->collation->dispatch($this);
        }
        return null;
    }

    protected function walkRangeItemAliases(nodes\range\FromElement $rangeItem): void
    {
        if (null !== $rangeItem->tableAlias) {
            $rangeItem->tableAlias->dispatch($this);
        }
        if (null !== $rangeItem->columnAliases) {
            $rangeItem->columnAliases->dispatch($this);
        }
    }

    public function walkRangeFunctionCall(nodes\range\FunctionCall $rangeItem)
    {
        $rangeItem->function->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkJoinExpression(nodes\range\JoinExpression $rangeItem)
    {
        $rangeItem->left->dispatch($this);
        $rangeItem->right->dispatch($this);
        if (null !== $rangeItem->on) {
            $rangeItem->on->dispatch($this);
        }
        if (null !== $rangeItem->using) {
            $rangeItem->using->dispatch($this);
        }
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem)
    {
        $rangeItem->name->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem)
    {
        $rangeItem->functions->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkRowsFromElement(nodes\range\RowsFromElement $node)
    {
        $node->function->dispatch($this);
        $node->columnAliases->dispatch($this);
        return null;
    }

    public function walkRangeSubselect(nodes\range\Subselect $rangeItem)
    {
        $rangeItem->query->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target)
    {
        $target->relation->dispatch($this);
        if (null !== $target->alias) {
            $target->alias->dispatch($this);
        }
        return null;
    }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target)
    {
        $this->walkInsertTarget($target);
        return null;
    }

    public function walkTableSample(nodes\range\TableSample $rangeItem)
    {
        $rangeItem->relation->dispatch($this);
        $rangeItem->method->dispatch($this);
        $rangeItem->arguments->dispatch($this);
        if (null !== $rangeItem->repeatable) {
            $rangeItem->repeatable->dispatch($this);
        }
        return null;
    }


    public function walkXmlElement(nodes\xml\XmlElement $xml)
    {
        $xml->name->dispatch($this);
        $xml->attributes->dispatch($this);
        $xml->content->dispatch($this);
        return null;
    }

    public function walkXmlExists(nodes\xml\XmlExists $xml)
    {
        $xml->xpath->dispatch($this);
        $xml->xml->dispatch($this);
        return null;
    }

    public function walkXmlForest(nodes\xml\XmlForest $xml)
    {
        $this->walkGenericNodeList($xml);
        return null;
    }

    public function walkXmlParse(nodes\xml\XmlParse $xml)
    {
        $xml->argument->dispatch($this);
        return null;
    }

    public function walkXmlPi(nodes\xml\XmlPi $xml)
    {
        $xml->name->dispatch($this);
        if (null !== $xml->content) {
            $xml->content->dispatch($this);
        }
        return null;
    }

    public function walkXmlRoot(nodes\xml\XmlRoot $xml)
    {
        $xml->xml->dispatch($this);
        if (null !== $xml->version) {
            $xml->version->dispatch($this);
        }
        return null;
    }

    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml)
    {
        $xml->argument->dispatch($this);
        $xml->type->dispatch($this);
        return null;
    }

    public function walkXmlTable(nodes\range\XmlTable $table)
    {
        $table->documentExpression->dispatch($this);
        $table->rowExpression->dispatch($this);
        $table->columns->dispatch($this);
        $table->namespaces->dispatch($this);
        $this->walkRangeItemAliases($table);
        return null;
    }

    public function walkXmlOrdinalityColumnDefinition(nodes\xml\XmlOrdinalityColumnDefinition $column)
    {
        $column->name->dispatch($this);
        return null;
    }

    public function walkXmlTypedColumnDefinition(nodes\xml\XmlTypedColumnDefinition $column)
    {
        $column->name->dispatch($this);
        $column->type->dispatch($this);
        if (null !== $column->path) {
            $column->path->dispatch($this);
        }
        if (null !== $column->default) {
            $column->default->dispatch($this);
        }
        return null;
    }

    public function walkXmlNamespace(nodes\xml\XmlNamespace $ns)
    {
        $ns->value->dispatch($this);
        if (null !== $ns->alias) {
            $ns->alias->dispatch($this);
        }
        return null;
    }

    public function walkOnConflictClause(nodes\OnConflictClause $onConflict)
    {
        if (null !== $onConflict->target) {
            $onConflict->target->dispatch($this);
        }
        $onConflict->set->dispatch($this);
        $onConflict->where->dispatch($this);
        return null;
    }

    public function walkIndexParameters(nodes\IndexParameters $parameters)
    {
        $parameters->where->dispatch($this);
        $this->walkGenericNodeList($parameters);
        return null;
    }

    public function walkIndexElement(nodes\IndexElement $element)
    {
        $element->expression->dispatch($this);
        if (null !== $element->collation) {
            $element->collation->dispatch($this);
        }
        if (null !== $element->opClass) {
            $element->opClass->dispatch($this);
        }
        return null;
    }


    public function walkEmptyGroupingSet(nodes\group\EmptyGroupingSet $empty)
    {
        return null;
    }

    public function walkCubeOrRollupClause(nodes\group\CubeOrRollupClause $clause)
    {
        return $this->walkGenericNodeList($clause);
    }

    public function walkGroupingSetsClause(nodes\group\GroupingSetsClause $clause)
    {
        return $this->walkGenericNodeList($clause);
    }

    public function walkGroupByClause(nodes\group\GroupByClause $clause)
    {
        return $this->walkGenericNodeList($clause);
    }

    public function walkSearchClause(nodes\cte\SearchClause $clause)
    {
        $clause->trackColumns->dispatch($this);
        $clause->sequenceColumn->dispatch($this);
        return null;
    }

    public function walkCycleClause(nodes\cte\CycleClause $clause)
    {
        $clause->trackColumns->dispatch($this);
        $clause->markColumn->dispatch($this);
        $clause->pathColumn->dispatch($this);
        if (null !== $clause->markValue) {
            $clause->markValue->dispatch($this);
        }
        if (null !== $clause->markDefault) {
            $clause->markDefault->dispatch($this);
        }
        return null;
    }

    public function walkUsingClause(nodes\range\UsingClause $clause)
    {
        $this->walkGenericNodeList($clause);
        if (null !== $clause->alias) {
            $clause->alias->dispatch($this);
        }
        return null;
    }

    public function walkMergeStatement(Merge $statement)
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->using->dispatch($this);
        $statement->on->dispatch($this);
        $statement->when->dispatch($this);
        return null;
    }

    public function walkMergeDelete(nodes\merge\MergeDelete $clause)
    {
        return null;
    }

    public function walkMergeInsert(nodes\merge\MergeInsert $clause)
    {
        $clause->cols->dispatch($this);
        if (null !== $clause->values) {
            $clause->values->dispatch($this);
        }
        return null;
    }

    public function walkMergeUpdate(nodes\merge\MergeUpdate $clause)
    {
        $clause->set->dispatch($this);
        return null;
    }

    public function walkMergeValues(nodes\merge\MergeValues $clause)
    {
        $this->walkGenericNodeList($clause);
        return null;
    }

    public function walkMergeWhenMatched(nodes\merge\MergeWhenMatched $clause)
    {
        if (null !== $clause->condition) {
            $clause->condition->dispatch($this);
        }
        if (null !== $clause->action) {
            $clause->action->dispatch($this);
        }
        return null;
    }

    public function walkMergeWhenNotMatched(nodes\merge\MergeWhenNotMatched $clause)
    {
        if (null !== $clause->condition) {
            $clause->condition->dispatch($this);
        }
        if (null !== $clause->action) {
            $clause->action->dispatch($this);
        }
        return null;
    }

    public function walkIsJsonExpression(nodes\expressions\IsJsonExpression $expression)
    {
        $expression->argument->dispatch($this);
        return null;
    }
}
