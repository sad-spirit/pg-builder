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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\expressions\LogicalExpression;

/**
 * A wrapper around ScalarExpression Nodes providing helper methods for building WHERE and HAVING clauses
 *
 * @property ScalarExpression|null $condition
 */
class WhereOrHavingClause extends GenericNode
{
    /** @var ScalarExpression|null */
    protected $p_condition;

    public function __construct(?ScalarExpression $condition = null)
    {
        $this->generatePropertyNames();
        $this->setCondition($condition);
    }

    /**
     * Sanity checks for $condition argument to various methods
     *
     * @throws InvalidArgumentException
     */
    private function normalizeCondition(string|null|self|ScalarExpression $condition): ?ScalarExpression
    {
        if ($condition instanceof self) {
            return $condition->p_condition;
        } elseif (is_string($condition)) {
            return $this->getParserOrFail('an expression')->parseExpression($condition);
        } else {
            return $condition;
        }
    }

    /**
     * Explicitly sets the expression for the clause
     *
     * @param string|ScalarExpression|WhereOrHavingClause|null $condition
     * @return $this
     */
    public function setCondition($condition = null): self
    {
        $this->setProperty($this->p_condition, $this->normalizeCondition($condition));
        return $this;
    }

    /**
     * Adds a condition to the clause using AND operator
     *
     * @param string|ScalarExpression|WhereOrHavingClause $condition
     * @return $this
     */
    public function and($condition): self
    {
        $nested = $condition instanceof self;
        if (null === ($condition = $this->normalizeCondition($condition))) {
            return $this;
        }

        if (!$this->p_condition) {
            if (
                $nested
                || ($condition instanceof LogicalExpression && LogicalExpression::AND !== $condition->operator)
            ) {
                // nested condition, should always wrap in LogicalExpression
                $this->p_condition = new LogicalExpression([$condition], LogicalExpression::AND);
                $this->p_condition->parentNode = $this;
            } else {
                $this->p_condition = $condition;
                $this->p_condition->setParentNode($this);
            }

        } else {
            if (!$this->p_condition instanceof LogicalExpression) {
                $this->p_condition = new LogicalExpression([$this->p_condition], LogicalExpression::AND);
                $this->p_condition->parentNode = $this;
            }
            if (
                LogicalExpression::AND === $this->p_condition->operator
                || null === ($key = $this->p_condition->lastKey())
            ) {
                $recipient = $this->p_condition;
            } else {
                $recipient = $this->p_condition[$key];
                if (!$recipient instanceof LogicalExpression || LogicalExpression::AND !== $recipient->operator) {
                    $this->p_condition[$key] = $recipient = new LogicalExpression(
                        [$recipient],
                        LogicalExpression::AND
                    );
                }
            }
            if ($condition instanceof LogicalExpression && LogicalExpression::AND === $condition->operator) {
                $recipient->merge($condition);
            } else {
                $recipient[] = $condition;
            }
        }

        return $this;
    }

    /**
     * Adds a condition to the clause using OR operator
     *
     * @param string|ScalarExpression|WhereOrHavingClause $condition
     * @return $this
     */
    public function or($condition): self
    {
        if (null === ($condition = $this->normalizeCondition($condition))) {
            return $this;
        }

        if (!$this->p_condition) {
            $this->p_condition = $condition;
            $this->p_condition->setParentNode($this);

        } else {
            if (
                !$this->p_condition instanceof LogicalExpression
                || LogicalExpression::OR !== $this->p_condition->operator
            ) {
                $this->p_condition = new LogicalExpression([$this->p_condition], LogicalExpression::OR);
                $this->p_condition->parentNode = $this;
            }

            if ($condition instanceof LogicalExpression && LogicalExpression::OR === $condition->operator) {
                $this->p_condition->merge($condition);
            } else {
                $this->p_condition[] = $condition;
            }
        }

        return $this;
    }

    /**
     * Helper method for creating nested conditions
     *
     * <code>
     * $select->where->and('foo')->and('bar')->or('baz');
     * </code>
     * will yield 'foo and bar or baz' in SQL output, while
     * <code>
     * $select->where->and('foo')->and($select->where->nested('bar')->or('baz'));
     * </code>
     * will yield 'foo and (bar or baz)'.
     *
     * @param string|ScalarExpression|WhereOrHavingClause $condition
     * @return WhereOrHavingClause
     */
    public function nested($condition): self
    {
        $condition = new self($this->normalizeCondition($condition));
        // $condition should have access to a Parser instance
        $condition->parentNode = $this;

        return $condition;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWhereOrHavingClause($this);
    }
}
