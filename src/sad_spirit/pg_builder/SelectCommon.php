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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    lists\LockList,
    lists\OrderByList,
    ScalarExpression
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

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
    public function __construct()
    {
        parent::__construct();

        $this->setNamedProperty('order', new OrderByList());
        $this->setNamedProperty('locking', new LockList());
        $this->props = array_merge($this->props, [
            'limit'         => null,
            'limitWithTies' => false,
            'offset'        => null
        ]);
    }

    private function normalizeExpression(&$expression, string $method): void
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
    }

    public function setLimit($limit = null): void
    {
        $this->normalizeExpression($limit, __METHOD__);
        $this->setNamedProperty('limit', $limit);
    }

    public function setLimitWithTies(bool $withTies): void
    {
        $this->setNamedProperty('limitWithTies', $withTies);
    }

    public function setOffset($offset = null): void
    {
        $this->normalizeExpression($offset, __METHOD__);
        $this->setNamedProperty('offset', $offset);
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
        if (!$this->getParentNode()) {
            $setOpSelect = new SetOpSelect($this, $select, $operator);

        } else {
            // $dummy is required here: if we pass $this to SetOpSelect's constructor, then by the time
            // control reaches replaceChild() $this will not be a child of parentNode anymore.
            $dummy       = new Select(new nodes\lists\TargetList());
            $setOpSelect = $this->getParentNode()->replaceChild($this, new SetOpSelect($dummy, $select, $operator));
            $setOpSelect->replaceChild($dummy, $this);
        }
        if ($parser = $this->getParser()) {
            $setOpSelect->setParser($parser);
        }

        return $setOpSelect;
    }
}
