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

namespace sad_spirit\pg_builder\nodes\merge;

use sad_spirit\pg_builder\enums\InsertOverriding;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\lists\SetTargetList;

/**
 * AST node representing INSERT action for MERGE statements
 *
 * @property SetTargetList         $cols
 * @property MergeValues|null      $values
 * @property InsertOverriding|null $overriding
 */
class MergeInsert extends GenericNode
{
    /** @internal Maps to `$cols` magic property, use the latter instead */
    protected SetTargetList $p_cols;
    /** @internal Maps to `$values` magic property, use the latter instead */
    protected ?MergeValues $p_values = null;
    /** @internal Maps to `$overriding` magic property, use the latter instead */
    protected ?InsertOverriding $p_overriding = null;

    public function __construct(
        ?SetTargetList $cols = null,
        ?MergeValues $values = null,
        ?InsertOverriding $overriding = null
    ) {
        $this->generatePropertyNames();

        $this->p_cols = $cols ?? new SetTargetList();
        $this->p_cols->setParentNode($this);

        if (null !== $values) {
            $this->p_values = $values;
            $this->p_values->setParentNode($this);
        }

        $this->setOverriding($overriding);
    }

    /** @internal Support method for `$values` magic property, use the property instead */
    public function setValues(?MergeValues $values): void
    {
        $this->setProperty($this->p_values, $values);
    }

    /** @internal Support method for `$overriding` magic property, use the property instead */
    public function setOverriding(?InsertOverriding $overriding): void
    {
        $this->p_overriding = $overriding;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeInsert($this);
    }
}
