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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\nodes\lists\LockList,
    sad_spirit\pg_builder\nodes\lists\OrderByList,
    sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Base class for SELECT-type statements
 *
 * @property-read OrderByList      $order
 * @property      ScalarExpression $limit
 * @property      ScalarExpression $offset
 * @property-read LockList         $locking
 */
abstract class SelectCommon extends Statement
{
    public function __construct()
    {
        parent::__construct();

        $this->props['order']   = new OrderByList();
        $this->props['limit']   = null;
        $this->props['offset']  = null;
        $this->props['locking'] = new nodes\lists\LockList();

        $this->props['order']->setParentNode($this);
        $this->props['locking']->setParentNode($this);
    }

    private function _normalizeExpression(&$expression, $method)
    {
        if (is_string($expression)) {
            if (!($parser = $this->getParser())) {
                throw new InvalidArgumentException("Passed a string as an expression without a Parser available");
            }
            $expression = $parser->parseExpression($expression);
        }
        if (!is_null($expression) && !($expression instanceof ScalarExpression)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an SQL string or an instance of ScalarExpression, %s given',
                $method, is_object($expression) ? 'object(' . get_class($expression) . ')' : gettype($expression)
            ));
        }
    }

    public function setLimit($limit = null)
    {
        $this->_normalizeExpression($limit, __METHOD__);
        $this->setNamedProperty('limit', $limit);
    }

    public function setOffset($offset = null)
    {
        $this->_normalizeExpression($offset, __METHOD__);
        $this->setNamedProperty('offset', $offset);
    }

    /**
     * Combines this select statement with another one using UNION [ALL] operator
     *
     * @param string|SelectCommon $select
     * @param bool                $distinct Use UNION (true) or UNION ALL (false) operator
     * @return SetOpSelect
     */
    public function union($select, $distinct = true)
    {
        return $this->_setOperation($select, 'union' . ($distinct ? '' : ' all'));
    }

    /**
     * Combines this select statement with another one using INTERSECT [ALL] operator
     *
     * @param string|SelectCommon $select
     * @param bool                $distinct Use INTERSECT (true) or INTERSECT ALL (false) operator
     * @return SetOpSelect
     */
    public function intersect($select, $distinct = true)
    {
        return $this->_setOperation($select, 'intersect' . ($distinct ? '' : ' all'));
    }

    /**
     * Combines this select statement with another one using EXCEPT [ALL] operator
     *
     * @param string|SelectCommon $select
     * @param bool                $distinct Use EXCEPT (true) or EXCEPT ALL (false) operator
     * @return SetOpSelect
     */
    public function except($select, $distinct = true)
    {
        return $this->_setOperation($select, 'except' . ($distinct ? '' : ' all'));
    }

    private function _setOperation($select, $operator)
    {
        if (is_string($select)) {
            if (!($parser = $this->getParser())) {
                throw new InvalidArgumentException("Passed a string as a SELECT statement without a Parser available");
            }
            $select = $parser->parseSelectStatement($select);
        }
        if (!($select instanceof self)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an SQL string or an instance of SelectCommon, %s given',
                __METHOD__, is_object($select) ? 'object(' . get_class($select) . ')' : gettype($select)
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