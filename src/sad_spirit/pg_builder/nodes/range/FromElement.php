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
    protected ?Identifier $p_tableAlias = null;
    /** @var IdentifierList|null  */
    protected ?NodeList $p_columnAliases = null;

    /**
     * Sets table and column aliases for a FROM clause item
     *
     * @param Identifier|null $tableAlias
     * @param IdentifierList|null $columnAliases
     */
    public function setAlias(?Identifier $tableAlias = null, ?NodeList $columnAliases = null): void
    {
        $this->setTableAlias($tableAlias);
        $this->setColumnAliases($columnAliases);
    }

    /**
     * Sets an alias for the FROM item itself
     */
    public function setTableAlias(?Identifier $tableAlias): void
    {
        $this->setProperty($this->p_tableAlias, $tableAlias);
    }

    /**
     * Sets aliases for columns of FROM item
     *
     * @param IdentifierList|null $columnAliases
     */
    public function setColumnAliases(?NodeList $columnAliases): void
    {
        if (null !== $columnAliases && !$columnAliases instanceof IdentifierList) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of IdentifierList for $columnAliases, %s given',
                __METHOD__,
                get_class($columnAliases)
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
        if (null === $this->parentNode) {
            return new JoinExpression($this, $fromElement, $joinType);

        } else {
            // $dummy is required here: if we pass $this to JoinExpression's constructor, then by the time
            // control reaches replaceChild() $this will not be a child of parentNode anymore.
            $dummy = new RelationReference(new QualifiedName('dummy'));
            /** @var JoinExpression $join */
            $join  = $this->parentNode->replaceChild(
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
