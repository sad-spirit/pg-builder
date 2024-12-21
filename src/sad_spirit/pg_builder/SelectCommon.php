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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    lists\LockList,
    lists\OrderByList,
    LockingElement,
    OrderByElement,
    ScalarExpression
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Base class for SELECT-type statements
 *
 * @psalm-property OrderByList $order
 * @psalm-property LockList    $locking
 *
 * @property OrderByList|OrderByElement[] $order
 * @property ScalarExpression|null        $limit
 * @property bool                         $limitWithTies
 * @property ScalarExpression|null        $offset
 * @property LockList|LockingElement[]    $locking
 */
abstract class SelectCommon extends Statement
{
    /**
     * Precedence for UNION [ALL] and EXCEPT [ALL] set operations
     */
    protected const PRECEDENCE_SETOP_UNION     = 1;

    /**
     * Precedence for INTERSECT [ALL] set operation
     */
    protected const PRECEDENCE_SETOP_INTERSECT = 2;

    /**
     * Precedence for a base SELECT / VALUES statement in set operations
     */
    protected const PRECEDENCE_SETOP_SELECT    = 3;

    /** @var OrderByList */
    protected $p_order;
    /** @var ScalarExpression|null */
    protected $p_limit;
    /** @var bool */
    protected $p_limitWithTies = false;
    /** @var ScalarExpression|null */
    protected $p_offset;
    /** @var LockList */
    protected $p_locking;

    public function __construct()
    {
        parent::__construct();

        $this->p_order = new OrderByList();
        $this->p_order->parentNode = $this;

        $this->p_locking = new LockList();
        $this->p_locking->parentNode = $this;
    }

    /**
     * Ensures that expression is a ScalarExpression instance, tries to parse the string
     *
     * @param string|ScalarExpression|null $expression
     * @param string                       $method
     * @return ScalarExpression|null
     */
    private function normalizeExpression($expression, string $method): ?ScalarExpression
    {
        if (is_string($expression)) {
            $expression = $this->getParserOrFail('an expression')->parseExpression($expression);
        }
        if (!is_null($expression) && !($expression instanceof ScalarExpression)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an SQL string or an instance of ScalarExpression, %s given',
                $method,
                is_object($expression) ? 'object(' . get_class($expression) . ')' : gettype($expression)
            ));
        }
        return $expression;
    }

    /**
     * Sets the node representing LIMIT clause
     *
     * @param string|ScalarExpression|null $limit
     */
    public function setLimit($limit = null): void
    {
        $this->setProperty($this->p_limit, $this->normalizeExpression($limit, __METHOD__));
    }

    public function setLimitWithTies(bool $withTies): void
    {
        $this->p_limitWithTies = $withTies;
    }

    /**
     * Sets the node representing OFFSET clause
     *
     * @param string|ScalarExpression|null $offset
     */
    public function setOffset($offset = null): void
    {
        $this->setProperty($this->p_offset, $this->normalizeExpression($offset, __METHOD__));
    }

    /**
     * Combines this select statement with another one using UNION [ALL] operator
     *
     * @param string|SelectCommon $select
     * @param bool                $distinct Use UNION (true) or UNION ALL (false) operator
     * @return SetOpSelect
     */
    public function union($select, bool $distinct = true): SetOpSelect
    {
        return $this->combineUsingSetOperation($select, $distinct ? SetOpSelect::UNION : SetOpSelect::UNION_ALL);
    }

    /**
     * Combines this select statement with another one using INTERSECT [ALL] operator
     *
     * @param string|SelectCommon $select
     * @param bool                $distinct Use INTERSECT (true) or INTERSECT ALL (false) operator
     * @return SetOpSelect
     */
    public function intersect($select, bool $distinct = true): SetOpSelect
    {
        return $this->combineUsingSetOperation(
            $select,
            $distinct ? SetOpSelect::INTERSECT : SetOpSelect::INTERSECT_ALL
        );
    }

    /**
     * Combines this select statement with another one using EXCEPT [ALL] operator
     *
     * @param string|SelectCommon $select
     * @param bool                $distinct Use EXCEPT (true) or EXCEPT ALL (false) operator
     * @return SetOpSelect
     */
    public function except($select, bool $distinct = true): SetOpSelect
    {
        return $this->combineUsingSetOperation($select, $distinct ? SetOpSelect::EXCEPT : SetOpSelect::EXCEPT_ALL);
    }

    /**
     * Combines this select statement with another one using the given operator
     *
     * @param string|SelectCommon $select
     * @param string              $operator
     * @return SetOpSelect
     */
    private function combineUsingSetOperation($select, string $operator): SetOpSelect
    {
        if (is_string($select)) {
            $select = $this->getParserOrFail('a SELECT statement')->parseSelectStatement($select);
        }
        if (!($select instanceof self)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an SQL string or an instance of SelectCommon, %s given',
                __METHOD__,
                is_object($select) ? 'object(' . get_class($select) . ')' : gettype($select)
            ));
        }
        if (null === $this->parentNode) {
            $setOpSelect = new SetOpSelect($this, $select, $operator);

        } else {
            // $dummy is required here: if we pass $this to SetOpSelect's constructor, then by the time
            // control reaches replaceChild() $this will not be a child of parentNode anymore.
            $dummy       = new Select(new nodes\lists\TargetList());
            /** @var SetOpSelect $setOpSelect  */
            $setOpSelect = $this->parentNode->replaceChild($this, new SetOpSelect($dummy, $select, $operator));
            $setOpSelect->replaceChild($dummy, $this);
        }
        if (null !== ($parser = $this->getParser())) {
            $setOpSelect->setParser($parser);
        }

        return $setOpSelect;
    }

    /**
     * Returns the relative precedence for this SelectCommon instance in set operations
     *
     * @return int One of PRECEDENCE_SETOP_* constants
     */
    public function getPrecedence(): int
    {
        return self::PRECEDENCE_SETOP_SELECT;
    }
}
