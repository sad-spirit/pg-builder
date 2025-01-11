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
 * A tree walker that extracts information about parameters' types and possibly replaces
 * named parameters with positional ones
 */
class ParameterWalker extends BlankWalker
{
    /**
     * Mapping parameter name => parameter number
     * @var array<string, int>
     */
    private array $namedParameterMap = [];

    /**
     * Parameter types extracted from typecasts
     * @var array<int, nodes\TypeName|null>
     */
    private array $parameterTypes    = [];

    /**
     * Constructor, specifies how to handle named parameters
     *
     * @param bool $keepNamedParameters Whether to leave NamedParameter nodes in the AST or replace them
     *                                  with PositionalParameter ones
     */
    public function __construct(private readonly bool $keepNamedParameters = false)
    {
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

    public function walkNamedParameter(nodes\expressions\NamedParameter $node): null
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
            $paramIdx = \count($this->namedParameterMap);
            $this->namedParameterMap[$node->name] = $paramIdx;
        }

        $this->extractParameterType($node, $paramIdx);

        if (!$this->keepNamedParameters && null !== ($parent = $node->getParentNode())) {
            $parent->replaceChild($node, new nodes\expressions\PositionalParameter($paramIdx + 1));
        }
        return null;
    }

    public function walkPositionalParameter(nodes\expressions\PositionalParameter $node): null
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
        } elseif (!\array_key_exists($idx, $this->parameterTypes)) {
            $this->parameterTypes[$idx] = null;
        }
    }

    /* Optimization: these may have child nodes but will not have parameters. No sense in visiting. */

    public function walkSQLValueFunction(nodes\expressions\SQLValueFunction $node): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkColumnReference(nodes\ColumnReference $node): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkLockingElement(nodes\LockingElement $node): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkQualifiedName(nodes\QualifiedName $node): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkQualifiedOperator(nodes\QualifiedOperator $node): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkTypeName(nodes\TypeName $node): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkColumnDefinition(nodes\range\ColumnDefinition $node): null
    {
        /* No Parameters here */
        return null;
    }

    protected function walkRangeItemAliases(nodes\range\FromElement $rangeItem): void
    {
        /* No Parameters here */
    }

    public function walkRelationReference(nodes\range\RelationReference $rangeItem): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkInsertTarget(nodes\range\InsertTarget $target): null
    {
        /* No Parameters here */
        return null;
    }

    public function walkUpdateOrDeleteTarget(nodes\range\UpdateOrDeleteTarget $target): null
    {
        /* No Parameters here */
        return null;
    }
}
