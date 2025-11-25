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

use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a target column (with possible indirection) for INSERT or UPDATE statements
 *
 * Indirection is represented by array offsets. Unlike normal `Indirection` nodes,
 * Star indirection is not possible as Postgres does not allow it:
 * > ERROR:  row expansion via "*" is not supported here
 *
 * @property Identifier $name
 * @extends NonAssociativeList<Identifier|ArrayIndexes, iterable<Identifier|ArrayIndexes>, Identifier|ArrayIndexes>
 */
class SetTargetElement extends NonAssociativeList
{
    use NonRecursiveNode;
    use HasBothPropsAndOffsets;

    protected Identifier $p_name;

    protected static function getAllowedElementClasses(): array
    {
        return [
            Identifier::class,
            ArrayIndexes::class
        ];
    }

    /**
     * SetTargetElement constructor
     *
     * @param array<int,Identifier|ArrayIndexes> $indirection
     */
    public function __construct(string|Identifier $name, array $indirection = [])
    {
        $this->generatePropertyNames();
        parent::__construct($indirection);

        $this->p_name = $name instanceof Identifier ? $name : new Identifier($name);
        $this->p_name->setParentNode($this);
    }

    /**
     * Sets the target column name
     */
    public function setName(string|Identifier $name): void
    {
        $this->setRequiredProperty($this->p_name, $name instanceof Identifier ? $name : new Identifier($name));
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSetTargetElement($this);
    }
}
