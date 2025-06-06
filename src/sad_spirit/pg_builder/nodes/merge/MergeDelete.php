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

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\NonRecursiveNode;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing DELETE action for MERGE statements
 */
class MergeDelete extends GenericNode
{
    use NonRecursiveNode;

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeDelete($this);
    }
}
