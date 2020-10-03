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

/**
 * Represents a set operator (UNION, INTERSECT, EXCEPT) applied to two select statements
 *
 * @property      SelectCommon $left
 * @property      SelectCommon $right
 * @property-read string       $operator
 */
class SetOpSelect extends SelectCommon
{
    public const UNION         = 'union';
    public const UNION_ALL     = 'union all';
    public const INTERSECT     = 'intersect';
    public const INTERSECT_ALL = 'intersect all';
    public const EXCEPT        = 'except';
    public const EXCEPT_ALL    = 'except all';

    private const ALLOWED_OPERATORS = [
        self::UNION         => true,
        self::UNION_ALL     => true,
        self::INTERSECT     => true,
        self::INTERSECT_ALL => true,
        self::EXCEPT        => true,
        self::EXCEPT_ALL    => true
    ];

    public function __construct(SelectCommon $left, SelectCommon $right, string $operator = self::UNION)
    {
        parent::__construct();

        if (!isset(self::ALLOWED_OPERATORS[$operator])) {
            throw new exceptions\InvalidArgumentException("Unknown set operator '{$operator}'");
        }

        $this->setLeft($left);
        $this->setRight($right);
        $this->props['operator'] = $operator;
    }

    public function setLeft(SelectCommon $left): void
    {
        $this->setNamedProperty('left', $left);
    }

    public function setRight(SelectCommon $right): void
    {
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSetOpSelectStatement($this);
    }
}
