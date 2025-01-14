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
    ScalarExpression,
    lists\TargetList,
    merge\MergeWhenList,
    range\FromElement,
    range\UpdateOrDeleteTarget
};

/**
 * AST node representing MERGE statement
 *
 * @property UpdateOrDeleteTarget $relation
 * @property FromElement          $using
 * @property ScalarExpression     $on
 * @property MergeWhenList        $when
 * @property TargetList           $returning
 */
class Merge extends Statement
{
    protected UpdateOrDeleteTarget $p_relation;
    protected FromElement $p_using;
    protected ScalarExpression $p_on;
    protected MergeWhenList $p_when;
    protected TargetList $p_returning;

    public function __construct(
        UpdateOrDeleteTarget $relation,
        FromElement $using,
        ScalarExpression $on,
        ?MergeWhenList $when = null
    ) {
        parent::__construct();

        $this->p_relation = $relation;
        $this->p_relation->setParentNode($this);

        $this->p_using    = $using;
        $this->p_using->setParentNode($this);

        $this->p_on       = $on;
        $this->p_on->setParentNode($this);

        $this->p_when = $when ?? new MergeWhenList();
        $this->p_when->setParentNode($this);

        $this->p_returning = new TargetList();
        $this->p_returning->setParentNode($this);
    }

    public function setRelation(UpdateOrDeleteTarget $relation): void
    {
        $this->setRequiredProperty($this->p_relation, $relation);
    }

    public function setUsing(FromElement $using): void
    {
        $this->setRequiredProperty($this->p_using, $using);
    }

    public function setOn(ScalarExpression $on): void
    {
        $this->setRequiredProperty($this->p_on, $on);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeStatement($this);
    }
}
