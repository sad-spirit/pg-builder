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
    ScalarExpression,
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
 * @property ReturningClause      $returning
 */
class Merge extends Statement
{
    /** @internal Maps to `$relation` magic property, use the latter instead */
    protected UpdateOrDeleteTarget $p_relation;
    /** @internal Maps to `$using` magic property, use the latter instead */
    protected FromElement $p_using;
    /** @internal Maps to `$on` magic property, use the latter instead */
    protected ScalarExpression $p_on;
    /** @internal Maps to `$when` magic property, use the latter instead */
    protected MergeWhenList $p_when;
    /** @internal Maps to `$returning` magic property, use the latter instead */
    protected ReturningClause $p_returning;

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

        $this->p_returning = new ReturningClause();
        $this->p_returning->setParentNode($this);
    }

    /** @internal Support method for `$relation` magic property, use the property instead */
    public function setRelation(UpdateOrDeleteTarget $relation): void
    {
        $this->setRequiredProperty($this->p_relation, $relation);
    }

    /** @internal Support method for `$using` magic property, use the property instead */
    public function setUsing(FromElement $using): void
    {
        $this->setRequiredProperty($this->p_using, $using);
    }

    /** @internal Support method for `$on` magic property, use the property instead */
    public function setOn(ScalarExpression $on): void
    {
        $this->setRequiredProperty($this->p_on, $on);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeStatement($this);
    }
}
