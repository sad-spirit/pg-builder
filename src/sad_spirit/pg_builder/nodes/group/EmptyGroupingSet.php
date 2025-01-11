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

namespace sad_spirit\pg_builder\nodes\group;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\NonRecursiveNode,
    TreeWalker
};

/**
 * AST node representing empty grouping set '()' in GROUP BY clause
 */
class EmptyGroupingSet extends GenericNode implements GroupByElement
{
    use NonRecursiveNode;

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkEmptyGroupingSet($this);
    }
}
