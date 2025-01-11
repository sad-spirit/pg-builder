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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\lists\RowList;

/**
 * AST node representing VALUES statement
 *
 * @property RowList $rows
 */
class Values extends SelectCommon
{
    protected RowList $p_rows;

    public function __construct(RowList $rows)
    {
        parent::__construct();

        $rows->setParentNode($this);
        $this->p_rows = $rows;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkValuesStatement($this);
    }
}
