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

use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\{
    GenericNode,
    lists\SetClauseList
};

/**
 * AST node representing UPDATE action for MERGE statements
 *
 * @property SetClauseList $set
 */
class MergeUpdate extends GenericNode
{
    /** @internal Maps to `$set` magic property, use the latter instead */
    protected SetClauseList $p_set;

    public function __construct(SetClauseList $set)
    {
        $this->generatePropertyNames();

        $this->p_set = $set;
        $this->p_set->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeUpdate($this);
    }
}
