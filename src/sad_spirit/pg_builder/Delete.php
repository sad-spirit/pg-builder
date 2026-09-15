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
    ForPortionOfClause,
    ReturningClause,
    WhereOrHavingClause,
    lists\FromList,
    range\UpdateOrDeleteTarget
};

/**
 * AST node representing DELETE statement
 *
 * @property-read UpdateOrDeleteTarget $relation
 * @property      FromList             $using
 * @property-read WhereOrHavingClause  $where
 * @property      ReturningClause      $returning
 * @property      ?ForPortionOfClause  $forPortionOf
 */
class Delete extends Statement
{
    /** @internal Maps to `$relation` magic property, use the latter instead */
    protected UpdateOrDeleteTarget $p_relation;
    /** @internal Maps to `$using` magic property, use the latter instead */
    protected FromList $p_using;
    /** @internal Maps to `$where` magic property, use the latter instead */
    protected WhereOrHavingClause $p_where;
    /** @internal Maps to `$returning` magic property, use the latter instead */
    protected ReturningClause $p_returning;
    /** @internal Maps to `$forPortionOf` magic property, use the latter instead */
    protected ?ForPortionOfClause $p_forPortionOf = null;

    public function __construct(UpdateOrDeleteTarget $relation)
    {
        parent::__construct();

        $relation->setParentNode($this);
        $this->p_relation = $relation;

        $this->p_using     = new FromList();
        $this->p_returning = new ReturningClause();
        $this->p_where     = new WhereOrHavingClause();

        $this->p_using->parentNode     = \WeakReference::create($this);
        $this->p_returning->parentNode = \WeakReference::create($this);
        $this->p_where->parentNode     = \WeakReference::create($this);
    }

    /** @internal Support method for `$forPortionOf` magic property, use the property instead */
    public function setForPortionOf(ForPortionOfClause|string|null $forPortionOf): void
    {
        if (\is_string($forPortionOf)) {
            $forPortionOf = $this->getParserOrFail('FOR PORTION OF clause')->parseForPortionOfClause($forPortionOf);
        }
        $this->setProperty($this->p_forPortionOf, $forPortionOf);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkDeleteStatement($this);
    }
}
