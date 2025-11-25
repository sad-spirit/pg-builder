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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\enums\LogicalOperator;
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
    protected ScalarExpression|null $p_condition;

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
        } elseif (\is_string($condition)) {
            return $this->getParserOrFail('an expression')->parseExpression($condition);
        } else {
            return $condition;
        }
    }

    /**
     * Explicitly sets the expression for the clause
     *
     * @return $this
     */
    public function setCondition(string|null|self|ScalarExpression $condition): self
    {
        $this->setProperty($this->p_condition, $this->normalizeCondition($condition));
        return $this;
    }

    /**
     * Adds a condition to the clause using AND operator
     *
     * @return $this
     */
    public function and(self|string|ScalarExpression|null $condition): self
    {
        $nested = $condition instanceof self;
        if (null === ($condition = $this->normalizeCondition($condition))) {
            return $this;
        }

        if (!$this->p_condition) {
            if (
                $nested
                || ($condition instanceof LogicalExpression && LogicalOperator::AND !== $condition->operator)
            ) {
                // nested condition, should always wrap in LogicalExpression
                $this->p_condition = new LogicalExpression([$condition], LogicalOperator::AND);
                $this->p_condition->parentNode = \WeakReference::create($this);
            } else {
                $this->p_condition = $condition;
                $this->p_condition->setParentNode($this);
            }

        } else {
            if (!$this->p_condition instanceof LogicalExpression) {
                $this->p_condition = new LogicalExpression([$this->p_condition], LogicalOperator::AND);
                $this->p_condition->parentNode = \WeakReference::create($this);
            }
            if (
                LogicalOperator::AND === $this->p_condition->operator
                || null === ($key = $this->p_condition->lastKey())
            ) {
                $recipient = $this->p_condition;
            } else {
                $recipient = $this->p_condition[$key];
                if (!$recipient instanceof LogicalExpression || LogicalOperator::AND !== $recipient->operator) {
                    $this->p_condition[$key] = $recipient = new LogicalExpression(
                        [$recipient],
                        LogicalOperator::AND
                    );
                }
            }
            if ($condition instanceof LogicalExpression && LogicalOperator::AND === $condition->operator) {
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
     * @return $this
     */
    public function or(self|string|ScalarExpression|null $condition): self
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
                || LogicalOperator::OR !== $this->p_condition->operator
            ) {
                $this->p_condition = new LogicalExpression([$this->p_condition], LogicalOperator::OR);
                $this->p_condition->parentNode = \WeakReference::create($this);
            }

            if ($condition instanceof LogicalExpression && LogicalOperator::OR === $condition->operator) {
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
     * ```PHP
     * $select->where->and('foo')->and('bar')->or('baz');
     * ```
     * will yield `foo and bar or baz` in SQL output, while
     * ```PHP
     * $select->where->and('foo')->and($select->where->nested('bar')->or('baz'));
     * ```
     * will yield `foo and (bar or baz)`.
     */
    public function nested(self|string|ScalarExpression $condition): self
    {
        $condition = new self($this->normalizeCondition($condition));
        // $condition should have access to a Parser instance
        $condition->parentNode = \WeakReference::create($this);

        return $condition;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkWhereOrHavingClause($this);
    }
}
