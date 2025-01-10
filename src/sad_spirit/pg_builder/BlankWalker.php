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
 * @copyright 2014-2024 Alexey Borzov
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

    public function walkSelectStatement(Select $statement): mixed
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

    public function walkSetOpSelectStatement(SetOpSelect $statement): mixed
    {
        $statement->with->dispatch($this);
        $statement->left->dispatch($this);
        $statement->right->dispatch($this);

        $this->walkCommonSelectClauses($statement);
        return null;
    }

    public function walkValuesStatement(Values $statement): mixed
    {
        $statement->rows->dispatch($this);

        $this->walkCommonSelectClauses($statement);
        return null;
    }

    public function walkDeleteStatement(Delete $statement): mixed
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->using->dispatch($this);
        $statement->where->dispatch($this);
        $statement->returning->dispatch($this);
        return null;
    }

    public function walkInsertStatement(Insert $statement): mixed
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

    public function walkUpdateStatement(Update $statement): mixed
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->set->dispatch($this);
        $statement->from->dispatch($this);
        $statement->where->dispatch($this);
        $statement->returning->dispatch($this);
        return null;
    }


    public function walkArrayIndexes(nodes\ArrayIndexes $node): mixed
    {
        if (null !== $node->lower) {
            $node->lower->dispatch($this);
        }
        if (null !== $node->upper) {
            $node->upper->dispatch($this);
        }
        return null;
    }

    public function walkColumnReference(nodes\ColumnReference $node): mixed
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

    public function walkCommonTableExpression(nodes\CommonTableExpression $node): mixed
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

    public function walkKeywordConstant(nodes\expressions\KeywordConstant $node): mixed
    {
        return null;
    }

    public function walkNumericConstant(nodes\expressions\NumericConstant $node): mixed
    {
        return null;
    }

    public function walkStringConstant(nodes\expressions\StringConstant $node): mixed
    {
        return null;
    }

    public function walkFunctionCall(nodes\FunctionCall $node): mixed
    {
        $node->name->dispatch($this);
        $node->arguments->dispatch($this);
        $node->order->dispatch($this);
        return null;
    }

    public function walkSQLValueFunction(nodes\expressions\SQLValueFunction $node): mixed
    {
        if (null !== $node->modifier) {
            $node->modifier->dispatch($this);
        }
        return null;
    }

    public function walkSystemFunctionCall(nodes\expressions\SystemFunctionCall $node): mixed
    {
        $node->arguments->dispatch($this);
        return null;
    }

    public function walkIdentifier(nodes\Identifier $node): mixed
    {
        return null;
    }

    public function walkIndirection(nodes\Indirection $node): mixed
    {
        $node->expression->dispatch($this);
        $this->walkGenericNodeList($node);
        return null;
    }

    public function walkLockingElement(nodes\LockingElement $node): mixed
    {
        $this->walkGenericNodeList($node);
        return null;
    }

    public function walkOrderByElement(nodes\OrderByElement $node): mixed
    {
        $node->expression->dispatch($this);
        if ($node->operator instanceof nodes\QualifiedOperator) {
            $node->operator->dispatch($this);
        }
        return null;
    }

    public function walkNamedParameter(nodes\expressions\NamedParameter $node): mixed
    {
        return null;
    }

    public function walkPositionalParameter(nodes\expressions\PositionalParameter $node): mixed
    {
        return null;
    }

    public function walkQualifiedName(nodes\QualifiedName $node): mixed
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

    public function walkQualifiedOperator(nodes\QualifiedOperator $node): mixed
    {
        if (null !== $node->catalog) {
            $node->catalog->dispatch($this);
        }
        if (null !== $node->schema) {
            $node->schema->dispatch($this);
        }
        return null;
    }


    public function walkSetTargetElement(nodes\SetTargetElement $node): mixed
    {
        $node->name->dispatch($this);
        /** @var nodes\Identifier|nodes\ArrayIndexes $item */
        foreach ($node as $item) {
            $item->dispatch($this);
        }
        return null;
    }

    public function walkSingleSetClause(nodes\SingleSetClause $node): mixed
    {
        $node->column->dispatch($this);
        $node->value->dispatch($this);
        return null;
    }

    public function walkMultipleSetClause(nodes\MultipleSetClause $node): mixed
    {
        $node->columns->dispatch($this);
        $node->value->dispatch($this);
        return null;
    }

    public function walkSetToDefault(nodes\SetToDefault $node): mixed
    {
        return null;
    }

    public function walkStar(nodes\Star $node): mixed
    {
        return null;
    }

    public function walkTargetElement(nodes\TargetElement $node): mixed
    {
        $node->expression->dispatch($this);
        if (null !== $node->alias) {
            $node->alias->dispatch($this);
        }
        return null;
    }

    public function walkTypeName(nodes\TypeName $node): mixed
    {
        if (!($node instanceof nodes\IntervalTypeName)) {
            $node->name->dispatch($this);
        }
        $node->modifiers->dispatch($this);
        return null;
    }

    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node): mixed
    {
        if (null !== $node->condition) {
            $node->condition->dispatch($this);
        }
        return null;
    }

    public function walkWindowDefinition(nodes\WindowDefinition $node): mixed
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

    public function walkWindowFrameClause(nodes\WindowFrameClause $node): mixed
    {
        $node->start->dispatch($this);
        if (null !== $node->end) {
            $node->end->dispatch($this);
        }
        return null;
    }

    public function walkWindowFrameBound(nodes\WindowFrameBound $node): mixed
    {
        if (null !== $node->value) {
            $node->value->dispatch($this);
        }
        return null;
    }

    public function walkWithClause(nodes\WithClause $node): mixed
    {
        $this->walkGenericNodeList($node);
        return null;
    }

    public function walkArrayExpression(nodes\expressions\ArrayExpression $expression): mixed
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkArrayComparisonExpression(nodes\expressions\ArrayComparisonExpression $expression): mixed
    {
        $expression->array->dispatch($this);
        return null;
    }

    public function walkAtTimeZoneExpression(nodes\expressions\AtTimeZoneExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        $expression->timeZone->dispatch($this);
        return null;
    }

    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkCaseExpression(nodes\expressions\CaseExpression $expression): mixed
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

    public function walkCollateExpression(nodes\expressions\CollateExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        $expression->collation->dispatch($this);
        return null;
    }

    public function walkCollationForExpression(nodes\expressions\CollationForExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkExtractExpression(nodes\expressions\ExtractExpression $expression): mixed
    {
        $expression->source->dispatch($this);
        return null;
    }

    public function walkFunctionExpression(nodes\expressions\FunctionExpression $expression): mixed
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

    public function walkInExpression(nodes\expressions\InExpression $expression): mixed
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkIsDistinctFromExpression(nodes\expressions\IsDistinctFromExpression $expression): mixed
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkIsExpression(nodes\expressions\IsExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression): mixed
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkNormalizeExpression(nodes\expressions\NormalizeExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkNotExpression(nodes\expressions\NotExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkNullIfExpression(nodes\expressions\NullIfExpression $expression): mixed
    {
        $expression->first->dispatch($this);
        $expression->second->dispatch($this);
        return null;
    }

    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression): mixed
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

    public function walkOverlapsExpression(nodes\expressions\OverlapsExpression $expression): mixed
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
        return null;
    }

    public function walkOverlayExpression(nodes\expressions\OverlayExpression $expression): mixed
    {
        $expression->string->dispatch($this);
        $expression->newSubstring->dispatch($this);
        $expression->start->dispatch($this);
        if (null !== $expression->count) {
            $expression->count->dispatch($this);
        }
        return null;
    }

    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        $expression->pattern->dispatch($this);
        if (null !== $expression->escape) {
            $expression->escape->dispatch($this);
        }
        return null;
    }

    public function walkPositionExpression(nodes\expressions\PositionExpression $expression): mixed
    {
        $expression->substring->dispatch($this);
        $expression->string->dispatch($this);
        return null;
    }

    public function walkRowExpression(nodes\expressions\RowExpression $expression): mixed
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkSubselectExpression(nodes\expressions\SubselectExpression $expression): mixed
    {
        $expression->query->dispatch($this);
        return null;
    }

    public function walkSubstringFromExpression(nodes\expressions\SubstringFromExpression $expression): mixed
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

    public function walkSubstringSimilarExpression(nodes\expressions\SubstringSimilarExpression $expression): mixed
    {
        $expression->string->dispatch($this);
        $expression->pattern->dispatch($this);
        $expression->escape->dispatch($this);
        return null;
    }

    public function walkTrimExpression(nodes\expressions\TrimExpression $expression): mixed
    {
        $expression->arguments->dispatch($this);
        return null;
    }

    public function walkTypecastExpression(nodes\expressions\TypecastExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        $expression->type->dispatch($this);
        return null;
    }

    public function walkConstantTypecastExpression(nodes\expressions\ConstantTypecastExpression $expression): mixed
    {
        return $this->walkTypecastExpression($expression);
    }

    public function walkGroupingExpression(nodes\expressions\GroupingExpression $expression): mixed
    {
        $this->walkGenericNodeList($expression);
        return null;
    }

    public function walkGenericNodeList(\Traversable $list): mixed
    {
        /** @var Node $item */
        foreach ($list as $item) {
            $item->dispatch($this);
        }
        return null;
    }

    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list): mixed
    {
        /** @var nodes\ScalarExpression $item */
        foreach ($list as $item) {
            $item->dispatch($this);
        }
        return null;
    }


    public function walkColumnDefinition(nodes\range\ColumnDefinition $node): mixed
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

    public function walkRangeFunctionCall(nodes\range\FunctionCall $rangeItem): mixed
    {
        $rangeItem->function->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkJoinExpression(nodes\range\JoinExpression $rangeItem): mixed
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

    public function walkRelationReference(nodes\range\RelationReference $rangeItem): mixed
    {
        $rangeItem->name->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem): mixed
    {
        $rangeItem->functions->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkRowsFromElement(nodes\range\RowsFromElement $node): mixed
    {
        $node->function->dispatch($this);
        $node->columnAliases->dispatch($this);
        return null;
    }

    public function walkRangeSubselect(nodes\range\Subselect $rangeItem): mixed
    {
        $rangeItem->query->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target): mixed
    {
        $target->relation->dispatch($this);
        if (null !== $target->alias) {
            $target->alias->dispatch($this);
        }
        return null;
    }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target): mixed
    {
        $this->walkInsertTarget($target);
        return null;
    }

    public function walkTableSample(nodes\range\TableSample $rangeItem): mixed
    {
        $rangeItem->relation->dispatch($this);
        $rangeItem->method->dispatch($this);
        $rangeItem->arguments->dispatch($this);
        if (null !== $rangeItem->repeatable) {
            $rangeItem->repeatable->dispatch($this);
        }
        return null;
    }


    public function walkXmlElement(nodes\xml\XmlElement $xml): mixed
    {
        $xml->name->dispatch($this);
        $xml->attributes->dispatch($this);
        $xml->content->dispatch($this);
        return null;
    }

    public function walkXmlExists(nodes\xml\XmlExists $xml): mixed
    {
        $xml->xpath->dispatch($this);
        $xml->xml->dispatch($this);
        return null;
    }

    public function walkXmlForest(nodes\xml\XmlForest $xml): mixed
    {
        $this->walkGenericNodeList($xml);
        return null;
    }

    public function walkXmlParse(nodes\xml\XmlParse $xml): mixed
    {
        $xml->argument->dispatch($this);
        return null;
    }

    public function walkXmlPi(nodes\xml\XmlPi $xml): mixed
    {
        $xml->name->dispatch($this);
        if (null !== $xml->content) {
            $xml->content->dispatch($this);
        }
        return null;
    }

    public function walkXmlRoot(nodes\xml\XmlRoot $xml): mixed
    {
        $xml->xml->dispatch($this);
        if (null !== $xml->version) {
            $xml->version->dispatch($this);
        }
        return null;
    }

    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml): mixed
    {
        $xml->argument->dispatch($this);
        $xml->type->dispatch($this);
        return null;
    }

    public function walkXmlTable(nodes\range\XmlTable $table): mixed
    {
        $table->documentExpression->dispatch($this);
        $table->rowExpression->dispatch($this);
        $table->columns->dispatch($this);
        $table->namespaces->dispatch($this);
        $this->walkRangeItemAliases($table);
        return null;
    }

    public function walkXmlOrdinalityColumnDefinition(nodes\xml\XmlOrdinalityColumnDefinition $column): mixed
    {
        $column->name->dispatch($this);
        return null;
    }

    public function walkXmlTypedColumnDefinition(nodes\xml\XmlTypedColumnDefinition $column): mixed
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

    public function walkXmlNamespace(nodes\xml\XmlNamespace $ns): mixed
    {
        $ns->value->dispatch($this);
        if (null !== $ns->alias) {
            $ns->alias->dispatch($this);
        }
        return null;
    }

    public function walkOnConflictClause(nodes\OnConflictClause $onConflict): mixed
    {
        if (null !== $onConflict->target) {
            $onConflict->target->dispatch($this);
        }
        $onConflict->set->dispatch($this);
        $onConflict->where->dispatch($this);
        return null;
    }

    public function walkIndexParameters(nodes\IndexParameters $parameters): mixed
    {
        $parameters->where->dispatch($this);
        $this->walkGenericNodeList($parameters);
        return null;
    }

    public function walkIndexElement(nodes\IndexElement $element): mixed
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


    public function walkEmptyGroupingSet(nodes\group\EmptyGroupingSet $empty): mixed
    {
        return null;
    }

    public function walkCubeOrRollupClause(nodes\group\CubeOrRollupClause $clause): mixed
    {
        return $this->walkGenericNodeList($clause);
    }

    public function walkGroupingSetsClause(nodes\group\GroupingSetsClause $clause): mixed
    {
        return $this->walkGenericNodeList($clause);
    }

    public function walkGroupByClause(nodes\group\GroupByClause $clause): mixed
    {
        return $this->walkGenericNodeList($clause);
    }

    public function walkSearchClause(nodes\cte\SearchClause $clause): mixed
    {
        $clause->trackColumns->dispatch($this);
        $clause->sequenceColumn->dispatch($this);
        return null;
    }

    public function walkCycleClause(nodes\cte\CycleClause $clause): mixed
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

    public function walkUsingClause(nodes\range\UsingClause $clause): mixed
    {
        $this->walkGenericNodeList($clause);
        if (null !== $clause->alias) {
            $clause->alias->dispatch($this);
        }
        return null;
    }

    public function walkMergeStatement(Merge $statement): mixed
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->using->dispatch($this);
        $statement->on->dispatch($this);
        $statement->when->dispatch($this);
        return null;
    }

    public function walkMergeDelete(nodes\merge\MergeDelete $clause): mixed
    {
        return null;
    }

    public function walkMergeInsert(nodes\merge\MergeInsert $clause): mixed
    {
        $clause->cols->dispatch($this);
        if (null !== $clause->values) {
            $clause->values->dispatch($this);
        }
        return null;
    }

    public function walkMergeUpdate(nodes\merge\MergeUpdate $clause): mixed
    {
        $clause->set->dispatch($this);
        return null;
    }

    public function walkMergeValues(nodes\merge\MergeValues $clause): mixed
    {
        $this->walkGenericNodeList($clause);
        return null;
    }

    public function walkMergeWhenMatched(nodes\merge\MergeWhenMatched $clause): mixed
    {
        if (null !== $clause->condition) {
            $clause->condition->dispatch($this);
        }
        if (null !== $clause->action) {
            $clause->action->dispatch($this);
        }
        return null;
    }

    public function walkMergeWhenNotMatched(nodes\merge\MergeWhenNotMatched $clause): mixed
    {
        if (null !== $clause->condition) {
            $clause->condition->dispatch($this);
        }
        if (null !== $clause->action) {
            $clause->action->dispatch($this);
        }
        return null;
    }

    public function walkIsJsonExpression(nodes\expressions\IsJsonExpression $expression): mixed
    {
        $expression->argument->dispatch($this);
        return null;
    }

    public function walkJsonFormat(nodes\json\JsonFormat $clause): mixed
    {
        return null;
    }

    public function walkJsonReturning(nodes\json\JsonReturning $clause): mixed
    {
        $clause->type->dispatch($this);
        if (null !== $clause->format) {
            $clause->format->dispatch($this);
        }
        return null;
    }

    public function walkJsonFormattedValue(nodes\json\JsonFormattedValue $clause): mixed
    {
        $clause->expression->dispatch($this);
        if (null !== $clause->format) {
            $clause->format->dispatch($this);
        }
        return null;
    }

    public function walkJsonKeyValue(nodes\json\JsonKeyValue $clause): mixed
    {
        $clause->key->dispatch($this);
        $clause->value->dispatch($this);
        return null;
    }

    protected function walkCommonJsonAggregateFields(nodes\json\JsonAggregate $expression): void
    {
        if (null !== $expression->returning) {
            $expression->returning->dispatch($this);
        }
        if (null !== $expression->filter) {
            $expression->filter->dispatch($this);
        }
        if (null !== $expression->over) {
            $expression->over->dispatch($this);
        }
    }

    public function walkJsonArrayAgg(nodes\json\JsonArrayAgg $expression): mixed
    {
        $this->walkCommonJsonAggregateFields($expression);
        $expression->value->dispatch($this);
        if (null !== $expression->order) {
            $expression->order->dispatch($this);
        }
        return null;
    }

    public function walkJsonObjectAgg(nodes\json\JsonObjectAgg $expression): mixed
    {
        $this->walkCommonJsonAggregateFields($expression);
        $expression->keyValue->dispatch($this);
        return null;
    }

    public function walkJsonArrayValueList(nodes\json\JsonArrayValueList $expression): mixed
    {
        $expression->arguments->dispatch($this);
        if (null !== $expression->returning) {
            $expression->returning->dispatch($this);
        }
        return null;
    }

    public function walkJsonArraySubselect(nodes\json\JsonArraySubselect $expression): mixed
    {
        $expression->query->dispatch($this);
        if (null !== $expression->format) {
            $expression->format->dispatch($this);
        }
        if (null !== $expression->returning) {
            $expression->returning->dispatch($this);
        }
        return null;
    }

    public function walkJsonObject(nodes\json\JsonObject $expression): mixed
    {
        $expression->arguments->dispatch($this);
        if (null !== $expression->returning) {
            $expression->returning->dispatch($this);
        }
        return null;
    }

    public function walkJsonConstructor(nodes\json\JsonConstructor $expression): mixed
    {
        $expression->expression->dispatch($this);
        return null;
    }

    public function walkJsonScalar(nodes\json\JsonScalar $expression): mixed
    {
        $expression->expression->dispatch($this);
        return null;
    }

    public function walkJsonSerialize(nodes\json\JsonSerialize $expression): mixed
    {
        $expression->expression->dispatch($this);
        if (null !== $expression->returning) {
            $expression->returning->dispatch($this);
        }
        return null;
    }

    public function walkJsonArgument(nodes\json\JsonArgument $clause): mixed
    {
        $clause->value->dispatch($this);
        $clause->alias->dispatch($this);
        return null;
    }

    protected function walkCommonJsonQueryFields(nodes\json\JsonQueryCommon $expression): void
    {
        $expression->context->dispatch($this);
        $expression->path->dispatch($this);
        $expression->passing->dispatch($this);
    }

    public function walkJsonExists(nodes\json\JsonExists $expression): mixed
    {
        $this->walkCommonJsonQueryFields($expression);
        return null;
    }

    public function walkJsonValue(nodes\json\JsonValue $expression): mixed
    {
        $this->walkCommonJsonQueryFields($expression);
        if (null !== $expression->returning) {
            $expression->returning->dispatch($this);
        }
        if ($expression->onEmpty instanceof nodes\ScalarExpression) {
            $expression->onEmpty->dispatch($this);
        }
        if ($expression->onError instanceof nodes\ScalarExpression) {
            $expression->onError->dispatch($this);
        }
        return null;
    }

    public function walkJsonQuery(nodes\json\JsonQuery $expression): mixed
    {
        $this->walkCommonJsonQueryFields($expression);
        if (null !== $expression->returning) {
            $expression->returning->dispatch($this);
        }
        if ($expression->onEmpty instanceof nodes\ScalarExpression) {
            $expression->onEmpty->dispatch($this);
        }
        if ($expression->onError instanceof nodes\ScalarExpression) {
            $expression->onError->dispatch($this);
        }
        return null;
    }

    public function walkJsonTable(nodes\range\JsonTable $rangeItem): mixed
    {
        $rangeItem->context->dispatch($this);
        $rangeItem->path->dispatch($this);
        if (null !== $rangeItem->pathName) {
            $rangeItem->pathName->dispatch($this);
        }
        $rangeItem->passing->dispatch($this);
        $rangeItem->columns->dispatch($this);

        $this->walkRangeItemAliases($rangeItem);
        return null;
    }

    protected function walkJsonTypedColumnDefinition(nodes\range\json\JsonTypedColumnDefinition $column): void
    {
        $column->name->dispatch($this);
        $column->type->dispatch($this);
        if (null !== $column->path) {
            $column->path->dispatch($this);
        }
    }

    public function walkJsonExistsColumnDefinition(nodes\range\json\JsonExistsColumnDefinition $column): mixed
    {
        $this->walkJsonTypedColumnDefinition($column);
        return null;
    }

    public function walkJsonOrdinalityColumnDefinition(nodes\range\json\JsonOrdinalityColumnDefinition $column): mixed
    {
        $column->name->dispatch($this);
        return null;
    }

    public function walkJsonRegularColumnDefinition(nodes\range\json\JsonRegularColumnDefinition $column): mixed
    {
        $this->walkJsonTypedColumnDefinition($column);
        if (null !== $column->format) {
            $column->format->dispatch($this);
        }
        if ($column->onEmpty instanceof nodes\ScalarExpression) {
            $column->onEmpty->dispatch($this);
        }
        if ($column->onError instanceof nodes\ScalarExpression) {
            $column->onError->dispatch($this);
        }
        return null;
    }

    public function walkJsonNestedColumns(nodes\range\json\JsonNestedColumns $column): mixed
    {
        $column->path->dispatch($this);
        if (null !== $column->pathName) {
            $column->pathName->dispatch($this);
        }
        $column->columns->dispatch($this);
        return null;
    }
}
