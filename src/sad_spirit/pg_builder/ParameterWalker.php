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
 * A tree walker that extracts information about parameters' types and replaces
 * named parameters with positional ones
 */
class ParameterWalker extends BlankWalker
{
    /**
     * Mapping parameter name => parameter number
     * @var array
     */
    private $namedParameterMap = [];

    /**
     * Parameter types extracted from typecasts
     * @var array
     */
    private $parameterTypes    = [];

    /**
     * Returns mapping from parameter names to parameter numbers
     *
     * @return array
     */
    public function getNamedParameterMap(): array
    {
        return $this->namedParameterMap;
    }

    /**
     * Returns information about parameter types extracted from SQL typecasts
     *
     * @return array
     */
    public function getParameterTypes(): array
    {
        return $this->parameterTypes;
    }

    public function walkNamedParameter(nodes\expressions\NamedParameter $node)
    {
        if (empty($this->namedParameterMap) && !empty($this->parameterTypes)) {
            throw new exceptions\InvalidArgumentException(
                "Mixing named and positional parameters is not allowed; "
                . "found named parameter :{$node->name} after positional ones"
            );
        }

        if (isset($this->namedParameterMap[$node->name])) {
            $paramIdx = $this->namedParameterMap[$node->name];
        } else {
            $paramIdx = count($this->namedParameterMap);
            $this->namedParameterMap[$node->name] = $paramIdx;
        }

        $this->extractParameterType($node, $paramIdx);

        $node->getParentNode()->replaceChild($node, new nodes\expressions\PositionalParameter($paramIdx + 1));
    }

    public function walkPositionalParameter(nodes\expressions\PositionalParameter $node)
    {
        if (!empty($this->namedParameterMap)) {
            throw new exceptions\InvalidArgumentException(
                "Mixing named and positional parameters is not allowed; "
                . "found positional parameter \${$node->position} after named ones"
            );
        }
        $paramIdx = $node->position - 1;

        $this->extractParameterType($node, $paramIdx);
    }

    private function extractParameterType(nodes\expressions\Parameter $node, $idx)
    {
        if (!($parent = $node->getParentNode())) {
            throw new exceptions\InvalidArgumentException("Parameter node doesn't contain a link to a parent node");
        }
        if ($parent instanceof nodes\expressions\TypecastExpression && empty($this->parameterTypes[$idx])) {
            $this->parameterTypes[$idx] = clone $parent->type;
        } elseif (!array_key_exists($idx, $this->parameterTypes)) {
            $this->parameterTypes[$idx] = null;
        }
    }

    /* Optimization: these may have child nodes but will not have parameters. No sense in visiting. */

    public function walkColumnReference(nodes\ColumnReference $node)
    {
 /* No Parameters here */
    }

    public function walkLockingElement(nodes\LockingElement $node)
    {
 /* No Parameters here */
    }

    public function walkQualifiedName(nodes\QualifiedName $node)
    {
 /* No Parameters here */
    }

    public function walkQualifiedOperator(nodes\QualifiedOperator $node)
    {
 /* No Parameters here */
    }

    public function walkTypeName(nodes\TypeName $node)
    {
 /* No Parameters here */
    }

    public function walkColumnDefinition(nodes\range\ColumnDefinition $node)
    {
 /* No Parameters here */
    }

    protected function walkRangeItemAliases(nodes\range\FromElement $rangeItem)
    {
 /* No Parameters here */
    }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem)
    {
 /* No Parameters here */
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target)
    {
 /* No Parameters here */
    }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target)
    {
 /* No Parameters here */
    }
}
