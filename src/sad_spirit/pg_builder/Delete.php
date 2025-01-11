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

use sad_spirit\pg_builder\nodes\{
    lists\FromList,
    lists\TargetList,
    range\UpdateOrDeleteTarget,
    WhereOrHavingClause
};

/**
 * AST node representing DELETE statement
 *
 * @property-read UpdateOrDeleteTarget $relation
 * @property      FromList             $using
 * @property-read WhereOrHavingClause  $where
 * @property      TargetList           $returning
 */
class Delete extends Statement
{
    protected UpdateOrDeleteTarget $p_relation;
    protected FromList $p_using;
    protected WhereOrHavingClause $p_where;
    protected TargetList $p_returning;

    public function __construct(UpdateOrDeleteTarget $relation)
    {
        parent::__construct();

        $relation->setParentNode($this);
        $this->p_relation = $relation;

        $this->p_using     = new FromList();
        $this->p_returning = new TargetList();
        $this->p_where     = new WhereOrHavingClause();

        $this->p_using->parentNode     = $this;
        $this->p_returning->parentNode = $this;
        $this->p_where->parentNode     = $this;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkDeleteStatement($this);
    }
}
