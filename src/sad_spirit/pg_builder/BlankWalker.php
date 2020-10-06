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
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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
    protected function walkCommonSelectClauses(SelectCommon $statement)
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
    }

    public function walkSetOpSelectStatement(SetOpSelect $statement)
    {
        $statement->with->dispatch($this);
        $statement->left->dispatch($this);
        $statement->right->dispatch($this);

        $this->walkCommonSelectClauses($statement);
    }

    public function walkValuesStatement(Values $statement)
    {
        $statement->rows->dispatch($this);

        $this->walkCommonSelectClauses($statement);
    }

    public function walkDeleteStatement(Delete $statement)
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->using->dispatch($this);
        $statement->where->dispatch($this);
        $statement->returning->dispatch($this);
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
    }

    public function walkUpdateStatement(Update $statement)
    {
        $statement->with->dispatch($this);
        $statement->relation->dispatch($this);
        $statement->set->dispatch($this);
        $statement->from->dispatch($this);
        $statement->where->dispatch($this);
        $statement->returning->dispatch($this);
    }


    public function walkArrayIndexes(nodes\ArrayIndexes $node)
    {
        if (null !== $node->lower) {
            $node->lower->dispatch($this);
        }
        if (null !== $node->upper) {
            $node->upper->dispatch($this);
        }
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
    }

    public function walkCommonTableExpression(nodes\CommonTableExpression $node)
    {
        $node->statement->dispatch($this);
        $node->alias->dispatch($this);
        $node->columnAliases->dispatch($this);
    }

    public function walkConstant(nodes\Constant $node)
    {
    }

    public function walkFunctionCall(nodes\FunctionCall $node)
    {
        if ($node->name instanceof Node) {
            $node->name->dispatch($this);
        }
        $node->arguments->dispatch($this);
        $node->order->dispatch($this);
    }

    public function walkIdentifier(nodes\Identifier $node)
    {
    }

    public function walkIndirection(nodes\Indirection $node)
    {
        $node->expression->dispatch($this);
        $this->walkGenericNodeList($node);
    }

    public function walkLockingElement(nodes\LockingElement $node)
    {
        $this->walkGenericNodeList($node);
    }

    public function walkOrderByElement(nodes\OrderByElement $node)
    {
        $node->expression->dispatch($this);
        if ($node->operator instanceof nodes\QualifiedOperator) {
            $node->operator->dispatch($this);
        }
    }

    public function walkParameter(nodes\Parameter $node)
    {
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
    }

    public function walkQualifiedOperator(nodes\QualifiedOperator $node)
    {
        if (null !== $node->catalog) {
            $node->catalog->dispatch($this);
        }
        if (null !== $node->schema) {
            $node->schema->dispatch($this);
        }
    }


    public function walkSetTargetElement(nodes\SetTargetElement $node)
    {
        $node->name->dispatch($this);
        /* @var Node $item */
        foreach ($node as $item) {
            $item->dispatch($this);
        }
    }

    public function walkSingleSetClause(nodes\SingleSetClause $node)
    {
        $node->column->dispatch($this);
        $node->value->dispatch($this);
    }

    public function walkMultipleSetClause(nodes\MultipleSetClause $node)
    {
        $node->columns->dispatch($this);
        $node->value->dispatch($this);
    }

    public function walkSetToDefault(nodes\SetToDefault $node)
    {
    }

    public function walkStar(nodes\Star $node)
    {
    }

    public function walkTargetElement(nodes\TargetElement $node)
    {
        $node->expression->dispatch($this);
        if (null !== $node->alias) {
            $node->alias->dispatch($this);
        }
    }

    public function walkTypeName(nodes\TypeName $node)
    {
        if (!($node instanceof nodes\IntervalTypeName)) {
            $node->name->dispatch($this);
        }
        $node->modifiers->dispatch($this);
    }

    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node)
    {
        if (null !== $node->condition) {
            $node->condition->dispatch($this);
        }
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
    }

    public function walkWindowFrameClause(nodes\WindowFrameClause $node)
    {
        $node->start->dispatch($this);
        if (null !== $node->end) {
            $node->end->dispatch($this);
        }
    }

    public function walkWindowFrameBound(nodes\WindowFrameBound $node)
    {
        if (null !== $node->value) {
            $node->value->dispatch($this);
        }
    }

    public function walkWithClause(nodes\WithClause $node)
    {
        $this->walkGenericNodeList($node);
    }

    public function walkArrayExpression(nodes\expressions\ArrayExpression $expression)
    {
        $this->walkGenericNodeList($expression);
    }

    public function walkAtTimeZoneExpression(nodes\expressions\AtTimeZoneExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
    }

    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
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
    }

    public function walkCollateExpression(nodes\expressions\CollateExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->collation->dispatch($this);
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
    }

    public function walkInExpression(nodes\expressions\InExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
    }

    public function walkIsDistinctFromExpression(nodes\expressions\IsDistinctFromExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
    }

    public function walkIsExpression(nodes\expressions\IsExpression $expression)
    {
        $expression->argument->dispatch($this);
    }

    public function walkIsOfExpression(nodes\expressions\IsOfExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
    }

    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression)
    {
        $this->walkGenericNodeList($expression);
    }

    public function walkNotExpression(nodes\expressions\NotExpression $expression)
    {
        $expression->argument->dispatch($this);
    }

    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression)
    {
        if ($expression->operator instanceof nodes\QualifiedOperator) {
            $expression->operator->dispatch($this);
        }
        if (null !== $expression->left) {
            $expression->left->dispatch($this);
        }
        if (null !== $expression->right) {
            $expression->right->dispatch($this);
        }
    }

    public function walkOverlapsExpression(nodes\expressions\OverlapsExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
    }

    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->pattern->dispatch($this);
        if (null !== $expression->escape) {
            $expression->escape->dispatch($this);
        }
    }

    public function walkRowExpression(nodes\expressions\RowExpression $expression)
    {
        $this->walkGenericNodeList($expression);
    }

    public function walkSubselectExpression(nodes\expressions\SubselectExpression $expression)
    {
        $expression->query->dispatch($this);
    }

    public function walkTypecastExpression(nodes\expressions\TypecastExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->type->dispatch($this);
    }

    public function walkGroupingExpression(nodes\expressions\GroupingExpression $expression)
    {
        $this->walkGenericNodeList($expression);
    }

    public function walkGenericNodeList(NodeList $list)
    {
        /* @var Node $item */
        foreach ($list as $item) {
            $item->dispatch($this);
        }
    }

    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list)
    {
        $this->walkGenericNodeList($list);
    }


    public function walkColumnDefinition(nodes\range\ColumnDefinition $node)
    {
        $node->name->dispatch($this);
        $node->type->dispatch($this);
        if (null !== $node->collation) {
            $node->collation->dispatch($this);
        }
    }

    protected function walkRangeItemAliases(nodes\range\FromElement $rangeItem)
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
    }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem)
    {
        $rangeItem->name->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
    }

    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem)
    {
        $rangeItem->function->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
    }

    public function walkRowsFromElement(nodes\range\RowsFromElement $node)
    {
        $node->function->dispatch($this);
        $node->columnAliases->dispatch($this);
    }

    public function walkRangeSubselect(nodes\range\Subselect $rangeItem)
    {
        $rangeItem->query->dispatch($this);
        $this->walkRangeItemAliases($rangeItem);
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target)
    {
        $target->relation->dispatch($this);
        if (null !== $target->alias) {
            $target->alias->dispatch($this);
        }
    }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target)
    {
        $this->walkInsertTarget($target);
    }

    public function walkTableSample(nodes\range\TableSample $rangeItem)
    {
        $rangeItem->relation->dispatch($this);
        $rangeItem->method->dispatch($this);
        $rangeItem->arguments->dispatch($this);
        if (null !== $rangeItem->repeatable) {
            $rangeItem->repeatable->dispatch($this);
        }
    }


    public function walkXmlElement(nodes\xml\XmlElement $xml)
    {
        $xml->name->dispatch($this);
        $xml->attributes->dispatch($this);
        $xml->content->dispatch($this);
    }

    public function walkXmlForest(nodes\xml\XmlForest $xml)
    {
        $this->walkGenericNodeList($xml);
    }

    public function walkXmlParse(nodes\xml\XmlParse $xml)
    {
        $xml->argument->dispatch($this);
    }

    public function walkXmlPi(nodes\xml\XmlPi $xml)
    {
        $xml->name->dispatch($this);
        if (null !== $xml->content) {
            $xml->content->dispatch($this);
        }
    }

    public function walkXmlRoot(nodes\xml\XmlRoot $xml)
    {
        $xml->xml->dispatch($this);
        $xml->version->dispatch($this);
    }

    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml)
    {
        $xml->argument->dispatch($this);
        $xml->type->dispatch($this);
    }

    public function walkXmlTable(nodes\range\XmlTable $table)
    {
        $table->documentExpression->dispatch($this);
        $table->rowExpression->dispatch($this);
        $table->columns->dispatch($this);
        $table->namespaces->dispatch($this);
        $this->walkRangeItemAliases($table);
    }

    public function walkXmlColumnDefinition(nodes\xml\XmlColumnDefinition $column)
    {
        $column->name->dispatch($this);
        if (null !== $column->type) {
            $column->type->dispatch($this);
        }
        if (null !== $column->path) {
            $column->path->dispatch($this);
        }
        if (null !== $column->default) {
            $column->default->dispatch($this);
        }
    }

    public function walkXmlNamespace(nodes\xml\XmlNamespace $ns)
    {
        $ns->value->dispatch($this);
        if (null !== $ns->alias) {
            $ns->alias->dispatch($this);
        }
    }

    public function walkOnConflictClause(nodes\OnConflictClause $onConflict)
    {
        if (null !== $onConflict->target) {
            $onConflict->target->dispatch($this);
        }
        $onConflict->set->dispatch($this);
        $onConflict->where->dispatch($this);
    }

    public function walkIndexParameters(nodes\IndexParameters $parameters)
    {
        $parameters->where->dispatch($this);
        $this->walkGenericNodeList($parameters);
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
    }


    public function walkEmptyGroupingSet(nodes\group\EmptyGroupingSet $empty)
    {
    }

    public function walkCubeOrRollupClause(nodes\group\CubeOrRollupClause $clause)
    {
        return $this->walkGenericNodeList($clause);
    }

    public function walkGroupingSetsClause(nodes\group\GroupingSetsClause $clause)
    {
        return $this->walkGenericNodeList($clause);
    }
}
