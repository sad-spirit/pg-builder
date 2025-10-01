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
    ReturningClause,
    WhereOrHavingClause,
    lists\FromList,
    lists\SetClauseList,
    range\UpdateOrDeleteTarget
};

/**
 * AST node representing UPDATE statement
 *
 * @property-read UpdateOrDeleteTarget $relation
 * @property      SetClauseList        $set
 * @property      FromList             $from
 * @property-read WhereOrHavingClause  $where
 * @property      ReturningClause      $returning
 */
class Update extends Statement
{
    protected UpdateOrDeleteTarget $p_relation;
    protected SetClauseList $p_set;
    protected FromList $p_from;
    protected WhereOrHavingClause $p_where;
    protected ReturningClause $p_returning;

    public function __construct(UpdateOrDeleteTarget $relation, SetClauseList $set)
    {
        parent::__construct();

        $relation->setParentNode($this);
        $this->p_relation = $relation;

        $set->setParentNode($this);
        $this->p_set = $set;

        $this->p_from      = new FromList();
        $this->p_returning = new ReturningClause();
        $this->p_where     = new WhereOrHavingClause();

        $this->p_from->parentNode      = \WeakReference::create($this);
        $this->p_returning->parentNode = \WeakReference::create($this);
        $this->p_where->parentNode     = \WeakReference::create($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkUpdateStatement($this);
    }
}
