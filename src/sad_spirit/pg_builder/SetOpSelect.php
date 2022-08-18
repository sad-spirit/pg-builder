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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
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

    private const PRECEDENCES = [
        self::UNION         => self::PRECEDENCE_SETOP_UNION,
        self::UNION_ALL     => self::PRECEDENCE_SETOP_UNION,
        self::INTERSECT     => self::PRECEDENCE_SETOP_INTERSECT,
        self::INTERSECT_ALL => self::PRECEDENCE_SETOP_INTERSECT,
        self::EXCEPT        => self::PRECEDENCE_SETOP_UNION,
        self::EXCEPT_ALL    => self::PRECEDENCE_SETOP_UNION
    ];

    /** @var SelectCommon */
    protected $p_left;
    /** @var SelectCommon */
    protected $p_right;
    /** @var string */
    protected $p_operator;

    public function __construct(SelectCommon $left, SelectCommon $right, string $operator = self::UNION)
    {
        parent::__construct();

        if (!isset(self::PRECEDENCES[$operator])) {
            throw new exceptions\InvalidArgumentException("Unknown set operator '{$operator}'");
        }

        if ($left === $right) {
            throw new exceptions\InvalidArgumentException("Cannot combine a SELECT statement with itself");
        }

        $left->setParentNode($this);
        $this->p_left = $left;

        $right->setParentNode($this);
        $this->p_right = $right;

        $this->p_operator = $operator;
    }

    public function setLeft(SelectCommon $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function setRight(SelectCommon $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSetOpSelectStatement($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCES[$this->p_operator];
    }
}
