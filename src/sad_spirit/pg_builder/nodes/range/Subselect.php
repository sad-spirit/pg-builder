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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a subselect in FROM clause
 *
 * @property SelectCommon $query
 */
class Subselect extends LateralFromElement
{
    protected SelectCommon $p_query;

    public function __construct(SelectCommon $query)
    {
        $this->generatePropertyNames();

        $this->p_query = $query;
        $this->p_query->setParentNode($this);
    }

    public function setQuery(SelectCommon $query): void
    {
        $this->setRequiredProperty($this->p_query, $query);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkRangeSubselect($this);
    }
}
