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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents an array subscript [foo] or array slice [foo:bar] operation
 *
 * @property ScalarExpression|null $lower
 * @property ScalarExpression|null $upper
 * @property bool                  $isSlice
 */
class ArrayIndexes extends GenericNode
{
    protected ScalarExpression|null $p_lower;
    protected ScalarExpression|null $p_upper;
    protected bool $p_isSlice = false;

    public function __construct(
        ?ScalarExpression $lower = null,
        ?ScalarExpression $upper = null,
        bool $isSlice = false
    ) {
        $this->generatePropertyNames();

        if (null !== $lower && $upper === $lower) {
            throw new InvalidArgumentException("Cannot use the same Node for upper and lower bounds");
        }
        $this->p_isSlice = $isSlice;
        $this->setUpper($upper);
        $this->setLower($lower);
    }

    public function setLower(?ScalarExpression $lower): void
    {
        if (!$this->p_isSlice && null !== $lower) {
            throw new InvalidArgumentException("Lower bound should be omitted for non-slice ArrayIndexes");
        }
        $this->setProperty($this->p_lower, $lower);
    }

    public function setUpper(?ScalarExpression $upper): void
    {
        if (!$this->p_isSlice && null === $upper) {
            throw new InvalidArgumentException("Upper bound is required for non-slice ArrayIndexes");
        }
        $this->setProperty($this->p_upper, $upper);
    }

    public function setIsSlice(bool $isSlice): void
    {
        if (!$isSlice) {
            if (null === $this->p_upper) {
                throw new InvalidArgumentException("Upper bound is required for non-slice ArrayIndexes");
            }
            if (null !== $this->p_lower) {
                throw new InvalidArgumentException("Lower bound should be omitted for non-slice ArrayIndexes");
            }
        }
        $this->p_isSlice = $isSlice;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkArrayIndexes($this);
    }
}
