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

namespace sad_spirit\pg_builder;

/**
 * Represents a set operator (UNION, INTERSECT, EXCEPT) applied to two select statements
 *
 * @property      SelectCommon $left
 * @property      SelectCommon $right
 * @property-read string       $operator
 */
class SetOpSelect extends SelectCommon
{
    protected static $allowedOperators = [
        'union'         => true,
        'union all'     => true,
        'intersect'     => true,
        'intersect all' => true,
        'except'        => true,
        'except all'    => true
    ];

    public function __construct(SelectCommon $left, SelectCommon $right, $operator = 'union')
    {
        parent::__construct();

        if (!isset(self::$allowedOperators[$operator])) {
            throw new exceptions\InvalidArgumentException("Unknown set operator '{$operator}'");
        }

        $this->setLeft($left);
        $this->setRight($right);
        $this->props['operator'] = (string)$operator;
    }

    public function setLeft(SelectCommon $left)
    {
        $this->setNamedProperty('left', $left);
    }

    public function setRight(SelectCommon $right)
    {
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSetOpSelectStatement($this);
    }
}
