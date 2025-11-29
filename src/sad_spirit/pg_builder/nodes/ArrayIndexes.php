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
    /** @internal Maps to `$lower` magic property, use the latter instead */
    protected ScalarExpression|null $p_lower;
    /** @internal Maps to `$upper` magic property, use the latter instead */
    protected ScalarExpression|null $p_upper;
    /** @internal Maps to `$isSlice` magic property, use the latter instead */
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

    /** @internal Support method for `$lower` magic property, use the property instead */
    public function setLower(?ScalarExpression $lower): void
    {
        if (!$this->p_isSlice && null !== $lower) {
            throw new InvalidArgumentException("Lower bound should be omitted for non-slice ArrayIndexes");
        }
        $this->setProperty($this->p_lower, $lower);
    }

    /** @internal Support method for `$upper` magic property, use the property instead */
    public function setUpper(?ScalarExpression $upper): void
    {
        if (!$this->p_isSlice && null === $upper) {
            throw new InvalidArgumentException("Upper bound is required for non-slice ArrayIndexes");
        }
        $this->setProperty($this->p_upper, $upper);
    }

    /** @internal Support method for `$isSlice` magic property, use the property instead */
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
