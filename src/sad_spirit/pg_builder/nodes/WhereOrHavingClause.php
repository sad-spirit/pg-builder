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
    /** @var array{'condition': LogicalExpression|null} */
    protected $props = [
        'condition' => null
    ];

    public function __construct(ScalarExpression $condition = null)
    {
        $this->setCondition($condition);
    }

    /**
     * Sanity checks for $expression argument to various methods
     *
     * @param string|ScalarExpression|WhereOrHavingClause $condition
     * @param string                                      $method
     * @throws InvalidArgumentException
     */
    private function normalizeCondition(&$condition, string $method): void
    {
        if (is_string($condition)) {
            $condition = $this->getParserOrFail('an expression')->parseExpression($condition);
        }
        if (!($condition instanceof ScalarExpression) && !($condition instanceof self)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an SQL string or an instance of ScalarExpression or WhereOrHavingClause, %s given',
                $method,
                is_object($condition) ? 'object(' . get_class($condition) . ')' : gettype($condition)
            ));
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
        if (null !== $condition) {
            $this->normalizeCondition($condition, __METHOD__);
        }
        $this->setNamedProperty('condition', $condition instanceof self ? $condition->condition : $condition);

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
        $this->normalizeCondition($condition, __METHOD__);
        if (!$this->props['condition']) {
            if (
                $condition instanceof self
                || ($condition instanceof LogicalExpression && LogicalExpression::AND !== $condition->operator)
            ) {
                // nested condition, should always wrap in LogicalExpression
                $this->setNamedProperty('condition', new LogicalExpression(
                    [$condition instanceof self ? $condition->condition : $condition],
                    LogicalExpression::AND
                ));

            } else {
                $this->setNamedProperty('condition', $condition);
            }

        } else {
            if (!($this->props['condition'] instanceof LogicalExpression)) {
                $this->setNamedProperty('condition', new LogicalExpression(
                    [$this->props['condition']],
                    LogicalExpression::AND
                ));
            }
            if (LogicalExpression::AND === $this->props['condition']->operator) {
                $recipient = $this->props['condition'];
            } else {
                $key = $recipient = null;
                // empty loop is intentional, we just need last key and element in list
                foreach ($this->props['condition'] as $key => $recipient) {
                }
                if (!($recipient instanceof LogicalExpression) || LogicalExpression::AND !== $recipient->operator) {
                    $this->props['condition'][$key] = $recipient = new LogicalExpression(
                        [$recipient],
                        LogicalExpression::AND
                    );
                }
            }
            if ($condition instanceof LogicalExpression && LogicalExpression::AND === $condition->operator) {
                $recipient->merge($condition);
            } elseif ($condition instanceof self) { // we assume this should be "nested"
                $recipient[] = $condition->condition;
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
        if (!$this->props['condition']) {
            $this->setCondition($condition);

        } else {
            $this->normalizeCondition($condition, __METHOD__);
            if (
                !($this->props['condition'] instanceof LogicalExpression)
                || LogicalExpression::OR !== $this->props['condition']->operator
            ) {
                $this->setNamedProperty('condition', new LogicalExpression(
                    [$this->props['condition']],
                    LogicalExpression::OR
                ));
            }

            if ($condition instanceof LogicalExpression && LogicalExpression::OR === $condition->operator) {
                $this->props['condition']->merge($condition);
            } elseif ($condition instanceof self) { // we assume this should be "nested"
                $this->props['condition'][] = $condition->condition;
            } else {
                $this->props['condition'][] = $condition;
            }
        }

        return $this;
    }

    /**
     * Helper method for creating nested conditions
     *
     * <code>
     * $select->where->and_('foo')->and_('bar')->or_('baz');
     * </code>
     * will yield 'foo and bar or baz' in SQL output, while
     * <code>
     * $select->where->and_('foo')->and_($select->where->nested('bar')->or_('baz'));
     * </code>
     * will yield 'foo and (bar or baz)'.
     *
     * @param string|ScalarExpression|WhereOrHavingClause $condition
     * @return WhereOrHavingClause
     */
    public function nested($condition): self
    {
        $this->normalizeCondition($condition, __METHOD__);
        if (!($condition instanceof self)) {
            $condition = new self($condition);
        }
        // $condition should have access to a Parser instance
        $condition->setParentNode($this);
        return $condition;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWhereOrHavingClause($this);
    }
}
