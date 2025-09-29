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

use sad_spirit\pg_builder\enums\SetOperatorPrecedence;
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    lists\LockList,
    lists\OrderByList
};
use sad_spirit\pg_builder\enums\SetOperator;

/**
 * Base class for SELECT-type statements
 *
 * @property OrderByList           $order
 * @property ScalarExpression|null $limit
 * @property bool                  $limitWithTies
 * @property ScalarExpression|null $offset
 * @property LockList              $locking
 */
abstract class SelectCommon extends Statement
{
    protected OrderByList $p_order;
    protected ?ScalarExpression $p_limit = null;
    protected bool $p_limitWithTies = false;
    protected ?ScalarExpression $p_offset = null;
    protected LockList $p_locking;

    public function __construct()
    {
        parent::__construct();

        $this->p_order = new OrderByList();
        $this->p_order->parentNode = \WeakReference::create($this);

        $this->p_locking = new LockList();
        $this->p_locking->parentNode = \WeakReference::create($this);
    }

    /**
     * Ensures that expression is a ScalarExpression instance, tries to parse the string
     */
    private function normalizeExpression(string|ScalarExpression|null $expression): ?ScalarExpression
    {
        if (\is_string($expression)) {
            $expression = $this->getParserOrFail('an expression')->parseExpression($expression);
        }
        return $expression;
    }

    /**
     * Sets the node representing LIMIT clause
     */
    public function setLimit(string|ScalarExpression|null $limit): void
    {
        $this->setProperty($this->p_limit, $this->normalizeExpression($limit));
    }

    public function setLimitWithTies(bool $withTies): void
    {
        $this->p_limitWithTies = $withTies;
    }

    /**
     * Sets the node representing OFFSET clause
     */
    public function setOffset(string|ScalarExpression|null $offset): void
    {
        $this->setProperty($this->p_offset, $this->normalizeExpression($offset));
    }

    /**
     * Combines this select statement with another one using UNION [ALL] operator
     */
    public function union(string|self $select, bool $distinct = true): SetOpSelect
    {
        return $this->combineUsingSetOperation($select, $distinct ? SetOperator::UNION : SetOperator::UNION_ALL);
    }

    /**
     * Combines this select statement with another one using INTERSECT [ALL] operator
     */
    public function intersect(string|self $select, bool $distinct = true): SetOpSelect
    {
        return $this->combineUsingSetOperation(
            $select,
            $distinct ? SetOperator::INTERSECT : SetOperator::INTERSECT_ALL
        );
    }

    /**
     * Combines this select statement with another one using EXCEPT [ALL] operator
     */
    public function except(string|self $select, bool $distinct = true): SetOpSelect
    {
        return $this->combineUsingSetOperation($select, $distinct ? SetOperator::EXCEPT : SetOperator::EXCEPT_ALL);
    }

    /**
     * Combines this select statement with another one using the given operator
     */
    private function combineUsingSetOperation(string|self $select, SetOperator $operator): SetOpSelect
    {
        if (\is_string($select)) {
            $select = $this->getParserOrFail('a SELECT statement')->parseSelectStatement($select);
        }
        if (null === $parentNode = $this->getParentNode()) {
            $setOpSelect = new SetOpSelect($this, $select, $operator);

        } else {
            // $dummy is required here: if we pass $this to SetOpSelect's constructor, then by the time
            // control reaches replaceChild() $this will not be a child of parentNode anymore.
            $dummy       = new Select(new nodes\lists\TargetList());
            /** @var SetOpSelect $setOpSelect  */
            $setOpSelect = $parentNode->replaceChild($this, new SetOpSelect($dummy, $select, $operator));
            $setOpSelect->replaceChild($dummy, $this);
        }
        if (null !== ($parser = $this->getParser())) {
            $setOpSelect->setParser($parser);
        }

        return $setOpSelect;
    }

    /**
     * Returns the relative precedence for this SelectCommon instance in set operations
     */
    public function getPrecedence(): SetOperatorPrecedence
    {
        return SetOperatorPrecedence::SELECT;
    }
}
