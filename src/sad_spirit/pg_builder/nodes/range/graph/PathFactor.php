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

namespace sad_spirit\pg_builder\nodes\range\graph;

use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\expressions\NumericConstant;

/**
 * Contains an implementation of PathPrimary with possible quantifiers applied
 *
 * Quantifiers are parsed by Postgres, but not currently supported.
 *
 * @property PathPrimary     $pattern
 * @property NumericConstant $lower
 * @property NumericConstant $upper
 */
class PathFactor extends GenericNode
{
    protected PathPrimary $p_pattern;
    protected ?NumericConstant $p_lower = null;
    protected ?NumericConstant $p_upper = null;

    public function __construct(
        PathPrimary $pattern,
        ?NumericConstant $lower = null,
        ?NumericConstant $upper = null
    ) {
        if (null !== $lower && $lower === $upper) {
            throw new InvalidArgumentException("Cannot use the same Node for upper and lower bounds");
        }

        $this->generatePropertyNames();

        $this->p_pattern = $pattern;
        $this->p_pattern->setParentNode($this);

        $this->p_lower = $lower;
        $this->p_lower?->setParentNode($this);

        $this->p_upper = $upper;
        $this->p_upper?->setParentNode($this);
    }

    /** @internal Support method for `$pattern` magic property, use the property instead */
    public function setPattern(PathPrimary $pattern): void
    {
        $this->setRequiredProperty($this->p_pattern, $pattern);
    }

    /** @internal Support method for `$lower` magic property, use the property instead */
    public function setLower(?NumericConstant $lower): void
    {
        $this->setProperty($this->p_lower, $lower);
    }

    /** @internal Support method for `$upper` magic property, use the property instead */
    public function setUpper(?NumericConstant $upper): void
    {
        $this->setProperty($this->p_upper, $upper);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkPathFactor($this);
    }
}
