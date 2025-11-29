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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\{
    NodeList,
    enums\JoinType,
    exceptions\InvalidArgumentException,
    nodes\GenericNode,
    nodes\Identifier,
    nodes\QualifiedName
};
use sad_spirit\pg_builder\nodes\lists\IdentifierList;

/**
 * Base class for alias-able and join-able items in FROM clause
 *
 * @property Identifier|null     $tableAlias
 * @property IdentifierList|null $columnAliases
 */
abstract class FromElement extends GenericNode
{
    /** @internal Maps to `$tableAlias` magic property, use the latter instead */
    protected ?Identifier $p_tableAlias = null;
    /**
     * @var IdentifierList|null
     * @internal Maps to `$columnAliases` magic property, use the latter instead
     */
    protected ?NodeList $p_columnAliases = null;

    /**
     * Sets table and column aliases for a FROM clause item
     *
     * @param IdentifierList|null $columnAliases
     */
    public function setAlias(?Identifier $tableAlias, ?NodeList $columnAliases = null): void
    {
        $this->setTableAlias($tableAlias);
        $this->setColumnAliases($columnAliases);
    }

    /**
     * Sets an alias for the FROM item itself
     *
     * @internal Support method for `$tableAlias` magic property, use the property instead
     */
    public function setTableAlias(?Identifier $tableAlias): void
    {
        $this->setProperty($this->p_tableAlias, $tableAlias);
    }

    /**
     * Sets aliases for columns of FROM item
     *
     * @param IdentifierList|null $columnAliases
     * @internal Support method for `$columnAliases` magic property, use the property instead
     */
    public function setColumnAliases(?NodeList $columnAliases): void
    {
        if (null !== $columnAliases && !$columnAliases instanceof IdentifierList) {
            throw new InvalidArgumentException(\sprintf(
                '%s expects an instance of IdentifierList for $columnAliases, %s given',
                __METHOD__,
                $columnAliases::class
            ));
        }
        $this->setProperty($this->p_columnAliases, $columnAliases);
    }

    /**
     * Creates a JOIN between this element and another one using given join type
     */
    public function join(self|string $fromElement, JoinType $joinType = JoinType::INNER): JoinExpression
    {
        if (\is_string($fromElement)) {
            $fromElement = $this->getParserOrFail('a FROM element')->parseFromElement($fromElement);
        }
        if (null === $parentNode = $this->getParentNode()) {
            return new JoinExpression($this, $fromElement, $joinType);

        } else {
            // $dummy is required here: if we pass $this to JoinExpression's constructor, then by the time
            // control reaches replaceChild() $this will not be a child of parentNode anymore.
            $dummy = new RelationReference(new QualifiedName('dummy'));
            /** @var JoinExpression $join */
            $join  = $parentNode->replaceChild(
                $this,
                new JoinExpression($dummy, $fromElement, $joinType)
            );
            $join->replaceChild($dummy, $this);

            return $join;
        }
    }

    /**
     * Alias for join($fromElement, JoinType::INNER)
     */
    public function innerJoin(self|string $fromElement): JoinExpression
    {
        return $this->join($fromElement);
    }

    /**
     * Alias for join($fromElement, JoinType::CROSS)
     */
    public function crossJoin(self|string $fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinType::CROSS);
    }

    /**
     * Alias for join($fromElement, JoinType::LEFT)
     */
    public function leftJoin(self|string $fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinType::LEFT);
    }

    /**
     * Alias for join($fromElement, JoinType::RIGHT)
     */
    public function rightJoin(self|string $fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinType::RIGHT);
    }

    /**
     * Alias for join($fromElement, JoinType::FULL)
     */
    public function fullJoin(self|string $fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinType::FULL);
    }
}
