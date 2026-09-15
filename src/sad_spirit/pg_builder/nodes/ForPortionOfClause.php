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
 * Represents FOR PORTION OF clause of UPDATE and DELETE statements
 *
 * @property Identifier        $name
 * @property ?ScalarExpression $target
 * @property ?ScalarExpression $targetStart
 * @property ?ScalarExpression $targetEnd
 *
 * @since 3.4.0
 */
class ForPortionOfClause extends GenericNode
{
    /** @internal Maps to `$name` magic property, use the latter instead */
    protected Identifier $p_name;
    /** @internal Maps to `$target` magic property, use the latter instead */
    protected ?ScalarExpression $p_target;
    /** @internal Maps to `$targetStart` magic property, use the latter instead */
    protected ?ScalarExpression $p_targetStart;
    /** @internal Maps to `$targetEnd` magic property, use the latter instead */
    protected ?ScalarExpression $p_targetEnd;

    public function __construct(
        Identifier $name,
        ?ScalarExpression $target = null,
        ?ScalarExpression $targetStart = null,
        ?ScalarExpression $targetEnd = null
    ) {
        if (
            (null === $targetStart xor null === $targetEnd)
            || null === $target && null === $targetStart
            || null !== $target && null !== $targetStart
        ) {
            throw new InvalidArgumentException(
                'Either $target Node or $targetStart and $targetEnd Nodes should be given (but not both).'
            );
        }
        if (null !== $targetStart && $targetEnd === $targetStart) {
            throw new InvalidArgumentException(
                'Cannot use the same Node for $targetStart and $targetEnd.'
            );
        }

        $this->generatePropertyNames();

        $this->p_name = $name;
        $this->p_name->setParentNode($this);

        $this->p_target = $target;
        $this->p_target?->setParentNode($this);

        $this->p_targetStart = $targetStart;
        $this->p_targetStart?->setParentNode($this);

        $this->p_targetEnd = $targetEnd;
        $this->p_targetEnd?->setParentNode($this);
    }

    public function setName(Identifier $name): void
    {
        $this->setRequiredProperty($this->p_name, $name);
    }

    public function setTarget(ScalarExpression $target): void
    {
        if (null !== $this->p_targetStart) {
            throw new InvalidArgumentException(
                'Cannot set $target Node when $targetStart and $targetEnd were already set.'
            );
        }
        $this->setProperty($this->p_target, $target);
    }

    public function setTargetStart(ScalarExpression $targetStart): void
    {
        if (null !== $this->p_target) {
            throw new InvalidArgumentException('Cannot set $targetStart Node when $target was already set.');
        }
        $this->setProperty($this->p_targetStart, $targetStart);
    }

    public function setTargetEnd(ScalarExpression $targetEnd): void
    {
        if (null !== $this->p_target) {
            throw new InvalidArgumentException('Cannot set $targetEnd Node when $target was already set.');
        }
        $this->setProperty($this->p_target, $targetEnd);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkForPortionOfClause($this);
    }
}
