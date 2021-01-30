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
 * A tree walker that extracts information about parameters' types and possibly replaces
 * named parameters with positional ones
 */
class ParameterWalker extends BlankWalker
{
    /**
     * Mapping parameter name => parameter number
     * @var array<string, int>
     */
    private $namedParameterMap = [];

    /**
     * Parameter types extracted from typecasts
     * @var array<int, nodes\TypeName|null>
     */
    private $parameterTypes    = [];

    /**
     * Whether to leave NamedParameter nodes in the AST or replace them with PositionalParameter ones
     * @var bool
     */
    private $keepNamedParameters;

    /**
     * Constructor, specifies how to handle named parameters
     *
     * @param bool $keepNamedParameters
     */
    public function __construct(bool $keepNamedParameters = false)
    {
        $this->keepNamedParameters = $keepNamedParameters;
    }

    /**
     * Returns mapping from parameter names to parameter numbers
     *
     * @return array<string, int>
     */
    public function getNamedParameterMap(): array
    {
        return $this->namedParameterMap;
    }

    /**
     * Returns information about parameter types extracted from SQL typecasts
     *
     * @return array<int, nodes\TypeName|null>
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

        if (!$this->keepNamedParameters) {
            $node->getParentNode()->replaceChild($node, new nodes\expressions\PositionalParameter($paramIdx + 1));
        }
        return null;
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
        return null;
    }

    private function extractParameterType(nodes\expressions\Parameter $node, int $idx): void
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
        return null;
    }

    public function walkLockingElement(nodes\LockingElement $node)
    {
        /* No Parameters here */
        return null;
    }

    public function walkQualifiedName(nodes\QualifiedName $node)
    {
        /* No Parameters here */
        return null;
    }

    public function walkQualifiedOperator(nodes\QualifiedOperator $node)
    {
        /* No Parameters here */
        return null;
    }

    public function walkTypeName(nodes\TypeName $node)
    {
        /* No Parameters here */
        return null;
    }

    public function walkColumnDefinition(nodes\range\ColumnDefinition $node)
    {
        /* No Parameters here */
        return null;
    }

    protected function walkRangeItemAliases(nodes\range\FromElement $rangeItem): void
    {
        /* No Parameters here */
    }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem)
    {
        /* No Parameters here */
        return null;
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target)
    {
        /* No Parameters here */
        return null;
    }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target)
    {
        /* No Parameters here */
        return null;
    }
}
