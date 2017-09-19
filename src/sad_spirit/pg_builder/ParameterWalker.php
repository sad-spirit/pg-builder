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
class ParameterWalker extends BlankWalker
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

    /* Optimization: these may have child nodes but will not have parameters. No sense in visiting. */

    public function walkColumnReference(nodes\ColumnReference $node) { /* No Parameters here */ }

    public function walkLockingElement(nodes\LockingElement $node) { /* No Parameters here */ }

    public function walkQualifiedName(nodes\QualifiedName $node) { /* No Parameters here */ }

    public function walkTypeName(nodes\TypeName $node) { /* No Parameters here */ }

    public function walkColumnDefinition(nodes\range\ColumnDefinition $node) { /* No Parameters here */ }

    protected function walkRangeItemAliases(nodes\range\FromElement $rangeItem) { /* No Parameters here */ }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem) { /* No Parameters here */ }

    public function walkInsertTarget(nodes\range\InsertTarget $target) { /* No Parameters here */ }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target) { /* No Parameters here */ }
}
