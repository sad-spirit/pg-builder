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
 * A tree walker that extracts information about parameters' types and replaces
 * named parameters with positional ones
 */
class ParameterWalker implements TreeWalker
{
    protected $namedParameterMap = array();
    protected $parameterTypes    = array();

    public function getNamedParameterMap()
    {
        return $this->namedParameterMap;
    }

    public function getParameterTypes()
    {
        return $this->parameterTypes;
    }

    protected function walkCommonSelectClauses(SelectCommon $statement)
    {
        $statement->order->dispatch($this);
        if ($statement->limit) {
            $statement->limit->dispatch($this);
        }
        if ($statement->offset) {
            $statement->offset->dispatch($this);
        }
    }

    public function walkSelectStatement(Select $statement)
    {
        if ($statement->with) {
            $statement->with->dispatch($this);
        }

        $statement->list->dispatch($this);
        if ($statement->distinct instanceof Node) {
            $statement->distinct->dispatch($this);
        }
        $statement->from->dispatch($this);
        if ($statement->where) {
            $statement->where->dispatch($this);
        }
        $statement->group->dispatch($this);
        if ($statement->having) {
            $statement->having->dispatch($this);
        }
        $statement->window->dispatch($this);

        $this->walkCommonSelectClauses($statement);
    }

    public function walkSetOpSelectStatement(SetOpSelect $statement)
    {
        if ($statement->with) {
            $statement->with->dispatch($this);
        }

        $statement->left->dispatch($this);
        $statement->right->dispatch($this);

        $this->walkCommonSelectClauses($statement);
    }

    public function walkValuesStatement(Values $statement)
    {
        $this->walkGenericNodeList($statement->rows);

        $this->walkCommonSelectClauses($statement);
    }

    public function walkDeleteStatement(Delete $statement)
    {
        if ($statement->with) {
            $statement->with->dispatch($this);
        }
        $statement->using->dispatch($this);
        if ($statement->where) {
            $statement->where->dispatch($this);
        }
        $statement->returning->dispatch($this);
    }

    public function walkInsertStatement(Insert $statement)
    {
        if ($statement->with) {
            $statement->with->dispatch($this);
        }
        // unlikely this is needed...
        $statement->cols->dispatch($this);
        if ($statement->values) {
            $statement->values->dispatch($this);
        }
        $statement->returning->dispatch($this);
    }

    public function walkUpdateStatement(Update $statement)
    {
        if ($statement->with) {
            $statement->with->dispatch($this);
        }
        $statement->set->dispatch($this);
        $statement->from->dispatch($this);
        if ($statement->where) {
            $statement->where->dispatch($this);
        }
        $statement->returning->dispatch($this);
    }

    public function walkArrayIndexes(nodes\ArrayIndexes $node)
    {
        $node->lower->dispatch($this);
        if ($node->upper) {
            $node->upper->dispatch($this);
        }
    }

    public function walkColumnReference(nodes\ColumnReference $node) { /* No Parameters here */ }

    public function walkCommonTableExpression(nodes\CommonTableExpression $node)
    {
        $node->statement->dispatch($this);
    }

    public function walkConstant(nodes\Constant $node) { /* No Parameters here */ }

    public function walkFunctionCall(nodes\FunctionCall $node)
    {
        $node->arguments->dispatch($this);
        $node->order->dispatch($this);
    }

    public function walkIdentifier(nodes\Identifier $node) { /* No Parameters here */ }

    public function walkIndirection(nodes\Indirection $node)
    {
        $node->expression->dispatch($this);
        $this->walkGenericNodeList($node);
    }

    public function walkLockingElement(nodes\LockingElement $node) { /* No Parameters here */ }

    public function walkOrderByElement(nodes\OrderByElement $node)
    {
        $node->expression->dispatch($this);
    }

    public function walkParameter(nodes\Parameter $node)
    {
        switch ($node->type) {
        case Token::TYPE_POSITIONAL_PARAM:
            if (!empty($this->namedParameterMap)) {
                throw new exceptions\InvalidArgumentException(
                    "Mixing named and positional parameters is not allowed; "
                    . "found positional parameter \${$node->value} after named ones"
                );
            }
            $paramIdx = $node->value - 1;
            break;

        case Token::TYPE_NAMED_PARAM:
            if (empty($this->namedParameterMap) && !empty($this->parameterTypes)) {
                throw new exceptions\InvalidArgumentException(
                    "Mixing named and positional parameters is not allowed; "
                    . "found named parameter :{$node->value} after positional ones"
                );
            }
            if (isset($this->namedParameterMap[$node->value])) {
                $paramIdx = $this->namedParameterMap[$node->value];
            } else {
                $paramIdx = count($this->namedParameterMap);
                $this->namedParameterMap[$node->value] = $paramIdx;
            }
            break;

        default:
            throw new exceptions\InvalidArgumentException(sprintf('Unexpected parameter type %d', $node->type));
        }

        if (!($parent = $node->getParentNode())) {
            throw new exceptions\InvalidArgumentException("Parameter node doesn't contain a link to a parent node");
        }
        if ($parent instanceof nodes\expressions\TypecastExpression && empty($this->parameterTypes[$paramIdx])) {
            $this->parameterTypes[$paramIdx] = clone $parent->type;
        } elseif (!array_key_exists($paramIdx, $this->parameterTypes)) {
            $this->parameterTypes[$paramIdx] = null;
        }

        if (Token::TYPE_NAMED_PARAM === $node->type) {
            $parent->replaceChild($node, new nodes\Parameter($paramIdx + 1));
        }
    }

    public function walkQualifiedName(nodes\QualifiedName $node) { /* No Parameters here */ }

    public function walkSetToDefault(nodes\SetToDefault $node) { /* No Parameters here */ }

    public function walkStar(nodes\Star $node) { /* No Parameters here */ }

    public function walkSetTargetElement(nodes\SetTargetElement $node)
    {
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


    public function walkTargetElement(nodes\TargetElement $node)
    {
        $node->expression->dispatch($this);
    }

    public function walkTypeName(nodes\TypeName $node) { /* No Parameters here */ }

    public function walkWhereOrHavingClause(nodes\WhereOrHavingClause $node)
    {
        if ($node->condition) {
            $node->condition->dispatch($this);
        }
    }

    public function walkWindowDefinition(nodes\WindowDefinition $node)
    {
        if ($node->partition) {
            $node->partition->dispatch($this);
        }
        if ($node->order) {
            $node->order->dispatch($this);
        }
        // not sure whether parameters can actually appear here...
        if ($node->frameStart) {
            $node->frameStart->dispatch($this);
        }
        if ($node->frameEnd) {
            $node->frameEnd->dispatch($this);
        }
    }

    public function walkWindowFrameBound(nodes\WindowFrameBound $node)
    {
        if ($node->value) {
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

    public function walkBetweenExpression(nodes\expressions\BetweenExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
    }

    public function walkCaseExpression(nodes\expressions\CaseExpression $expression)
    {
        if ($expression->argument) {
            $expression->argument->dispatch($this);
        }
        /* @var nodes\expressions\WhenExpression $whenClause */
        foreach ($expression as $whenClause) {
            $whenClause->when->dispatch($this);
            $whenClause->then->dispatch($this);
        }
        if ($expression->else) {
            $expression->else->dispatch($this);
        }
    }

    public function walkCollateExpression(nodes\expressions\CollateExpression $expression)
    {
        $expression->argument->dispatch($this);
    }

    public function walkFunctionExpression(nodes\expressions\FunctionExpression $expression)
    {
        $this->walkFunctionCall($expression);
        if ($expression->filter) {
            $expression->filter->dispatch($this);
        }
        if ($expression->over) {
            $expression->over->dispatch($this);
        }
    }

    public function walkInExpression(nodes\expressions\InExpression $expression)
    {
        $expression->left->dispatch($this);
        $expression->right->dispatch($this);
    }

    public function walkIsOfExpression(nodes\expressions\IsOfExpression $expression)
    {
        $expression->left->dispatch($this);
    }

    public function walkLogicalExpression(nodes\expressions\LogicalExpression $expression)
    {
        $this->walkGenericNodeList($expression);
    }

    public function walkOperatorExpression(nodes\expressions\OperatorExpression $expression)
    {
        if ($expression->left) {
            $expression->left->dispatch($this);
        }
        if ($expression->right) {
            $expression->right->dispatch($this);
        }
    }

    public function walkPatternMatchingExpression(nodes\expressions\PatternMatchingExpression $expression)
    {
        $expression->argument->dispatch($this);
        $expression->pattern->dispatch($this);
        if ($expression->escape) {
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
    }

    /**
     * Most of the lists do not have any additional features and may be handled by a generic method
     *
     * @param NodeList $list
     * @return mixed
     */
    public function walkGenericNodeList(NodeList $list)
    {
        /* @var Node $item */
        foreach ($list as $item) {
            $item->dispatch($this);
        }
    }

    public function walkCtextRow(nodes\lists\CtextRow $list)
    {
        $this->walkGenericNodeList($list);
    }

    public function walkFunctionArgumentList(nodes\lists\FunctionArgumentList $list)
    {
        $this->walkGenericNodeList($list);
    }

    public function walkColumnDefinition(nodes\range\ColumnDefinition $node) { /* No Parameters here */ }

    public function walkRangeFunctionCall(nodes\range\FunctionCall $rangeItem)
    {
        $rangeItem->function->dispatch($this);
    }

    public function walkJoinExpression(nodes\range\JoinExpression $rangeItem)
    {
        $rangeItem->left->dispatch($this);
        $rangeItem->right->dispatch($this);
        if ($rangeItem->on) {
            $rangeItem->on->dispatch($this);
        }
    }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem) { /* No Parameters here */ }

    public function walkRangeSubselect(nodes\range\Subselect $rangeItem)
    {
        $rangeItem->query->dispatch($this);
    }

    public function walkRowsFrom(nodes\range\RowsFrom $rangeItem)
    {
        $rangeItem->function->dispatch($this);
    }

    public function walkRowsFromElement(nodes\range\RowsFromElement $node)
    {
        $node->function->dispatch($this);
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target) { /* No Parameters here */ }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target) { /* No Parameters here */ }


    public function walkXmlElement(nodes\xml\XmlElement $xml)
    {
        if ($xml->attributes) {
            $xml->attributes->dispatch($this);
        }
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
        $xml->content->dispatch($this);
    }

    public function walkXmlRoot(nodes\xml\XmlRoot $xml)
    {
        $xml->xml->dispatch($this);
        $xml->version->dispatch($this);
    }

    public function walkXmlSerialize(nodes\xml\XmlSerialize $xml)
    {
        $xml->argument->dispatch($this);
    }

    public function walkOnConflictClause(nodes\OnConflictClause $onConflict)
    {
        // Not sure whether IndexParameters may actually contain placeholders...
        if ($onConflict->target) {
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
    }
}
