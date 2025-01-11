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

use sad_spirit\pg_builder\nodes\lists\RowsFromList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a ROWS FROM() construct in FROM clause (PostgreSQL 9.4+)
 *
 * @property RowsFromList $functions
 */
class RowsFrom extends FunctionFromElement
{
    public function __construct(protected RowsFromList $p_functions)
    {
        $this->generatePropertyNames();
        $this->p_functions->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkRowsFrom($this);
    }
}
