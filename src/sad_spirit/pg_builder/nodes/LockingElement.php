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

use sad_spirit\pg_builder\enums\LockingStrength;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * AST node for locking options in SELECT clause
 *
 * @property-read LockingStrength $strength
 * @property-read bool            $noWait
 * @property-read bool            $skipLocked
 * @extends NonAssociativeList<QualifiedName, iterable<QualifiedName>, QualifiedName>
 */
class LockingElement extends NonAssociativeList
{
    use NonRecursiveNode;
    use HasBothPropsAndOffsets;

    protected LockingStrength $p_strength;
    protected bool $p_noWait;
    protected bool $p_skipLocked;

    protected static function getAllowedElementClasses(): array
    {
        return [QualifiedName::class];
    }

    /**
     * Constructor for LockingElement
     *
     * @param QualifiedName[] $relations
     */
    public function __construct(
        LockingStrength $strength,
        array $relations = [],
        bool $noWait = false,
        bool $skipLocked = false
    ) {
        if ($noWait && $skipLocked) {
            throw new InvalidArgumentException("Only one of NOWAIT or SKIP LOCKED is allowed in locking clause");
        }

        $this->generatePropertyNames();

        $this->p_strength   = $strength;
        $this->p_noWait     = $noWait;
        $this->p_skipLocked = $skipLocked;
        parent::__construct($relations);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkLockingElement($this);
    }
}
