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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    NodeList,
    nodes\GenericNode,
    nodes\Identifier,
    nodes\QualifiedName
};
use sad_spirit\pg_builder\nodes\lists\IdentifierList;

/**
 * Base class for alias-able items in FROM clause
 *
 * @property-read Identifier|null     $tableAlias
 * @property      IdentifierList|null $columnAliases
 */
abstract class FromElement extends GenericNode
{
    /** @var Identifier|null */
    protected $p_tableAlias;
    /** @var IdentifierList|null */
    protected $p_columnAliases;

    /**
     * Sets table and column aliases for a FROM clause item
     *
     * @param Identifier|null $tableAlias
     * @param IdentifierList|null $columnAliases
     */
    public function setAlias(Identifier $tableAlias = null, NodeList $columnAliases = null): void
    {
        if (null !== $columnAliases && !($columnAliases instanceof IdentifierList)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of IdentifierList for $columnAliases, %s given',
                __METHOD__,
                get_class($columnAliases)
            ));
        }

        $this->generatePropertyNames();
        $this->setProperty($this->p_tableAlias, $tableAlias);
        $this->setProperty($this->p_columnAliases, $columnAliases);
    }

    /**
     * Creates a JOIN between this element and another one using given join type
     *
     * @param string|FromElement $fromElement
     * @param string             $joinType
     * @return JoinExpression
     * @throws InvalidArgumentException
     */
    public function join($fromElement, string $joinType = JoinExpression::INNER): JoinExpression
    {
        if (is_string($fromElement)) {
            $fromElement = $this->getParserOrFail('a FROM element')->parseFromElement($fromElement);
        }
        if (!($fromElement instanceof self)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an SQL string or an instance of FromElement, %s given',
                __METHOD__,
                is_object($fromElement) ? 'object(' . get_class($fromElement) . ')' : gettype($fromElement)
            ));
        }
        if (null === $this->getParentNode()) {
            return new JoinExpression($this, $fromElement, strtolower($joinType));

        } else {
            // $dummy is required here: if we pass $this to JoinExpression's constructor, then by the time
            // control reaches replaceChild() $this will not be a child of parentNode anymore.
            $dummy = new RelationReference(new QualifiedName('dummy'));
            /** @var JoinExpression $join */
            $join  = $this->getParentNode()->replaceChild(
                $this,
                new JoinExpression($dummy, $fromElement, strtolower($joinType))
            );
            $join->replaceChild($dummy, $this);

            return $join;
        }
    }

    /**
     * Alias for join($fromElement, 'inner')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function innerJoin($fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinExpression::INNER);
    }

    /**
     * Alias for join($fromElement, 'cross')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function crossJoin($fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinExpression::CROSS);
    }

    /**
     * Alias for join($fromElement, 'left')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function leftJoin($fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinExpression::LEFT);
    }

    /**
     * Alias for join($fromElement, 'right')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function rightJoin($fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinExpression::RIGHT);
    }

    /**
     * Alias for join($fromElement, 'full')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function fullJoin($fromElement): JoinExpression
    {
        return $this->join($fromElement, JoinExpression::FULL);
    }
}
