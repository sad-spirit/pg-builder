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

use sad_spirit\pg_builder\nodes\lists\GroupByList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing GROUPING SETS(...) construct in GROUP BY clause
 */
class GroupingSetsClause extends GroupByList implements GroupByElement
{
    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkGroupingSetsClause($this);
    }
}
