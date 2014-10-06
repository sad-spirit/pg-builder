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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\lists\IdentifierList;

/**
 * Base class for alias-able items in FROM clause
 *
 * @property-read Identifier     $tableAlias
 * @property-read IdentifierList $columnAliases
 */
abstract class FromElement extends Node
{
    protected $props = array(
        'tableAlias'    => null,
        'columnAliases' => null
    );

    public function setAlias(Identifier $tableAlias = null, $columnAliases = null)
    {
        if (null !== $columnAliases && !($columnAliases instanceof IdentifierList)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of IdentifierList for $columnAliases, %s given',
                __METHOD__, is_object($columnAliases) ? 'object(' . get_class($columnAliases) . ')' : gettype($columnAliases)
            ));
        }
        $this->setNamedProperty('tableAlias', $tableAlias);
        $this->setNamedProperty('columnAliases', $columnAliases);
    }

    /**
     * Creates a JOIN between this element and another one using given join type
     *
     * @param string|FromElement $fromElement
     * @param string             $joinType
     * @return JoinExpression
     * @throws InvalidArgumentException
     */
    public function join($fromElement, $joinType = 'inner')
    {
        if (is_string($fromElement)) {
            if (!($parser = $this->getParser())) {
                throw new InvalidArgumentException("Passed a string as a FROM element without a Parser available");
            }
            $fromElement = $parser->parseFromElement($fromElement);
        }
        if (!($fromElement instanceof self)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an SQL string or an instance of FromElement, %s given',
                __METHOD__, is_object($fromElement) ? 'object(' . get_class($fromElement) . ')' : gettype($fromElement)
            ));
        }
        if (!$this->getParentNode()) {
            return new JoinExpression($this, $fromElement, strtolower($joinType));

        } else {
            // $dummy is required here: if we pass $this to JoinExpression's constructor, then by the time
            // control reaches replaceChild() $this will not be a child of parentNode anymore.
            $dummy = new RelationReference(new QualifiedName(array('dummy')));
            $join  = $this->getParentNode()->replaceChild(
                $this, new JoinExpression($dummy, $fromElement, strtolower($joinType))
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
    public function innerJoin($fromElement)
    {
        return $this->join($fromElement, 'inner');
    }

    /**
     * Alias for join($fromElement, 'cross')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function crossJoin($fromElement)
    {
        return $this->join($fromElement, 'cross');
    }

    /**
     * Alias for join($fromElement, 'left')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function leftJoin($fromElement)
    {
        return $this->join($fromElement, 'left');
    }

    /**
     * Alias for join($fromElement, 'right')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function rightJoin($fromElement)
    {
        return $this->join($fromElement, 'right');
    }

    /**
     * Alias for join($fromElement, 'full')
     *
     * @param string|FromElement $fromElement
     * @return JoinExpression
     */
    public function fullJoin($fromElement)
    {
        return $this->join($fromElement, 'full');
    }
}
