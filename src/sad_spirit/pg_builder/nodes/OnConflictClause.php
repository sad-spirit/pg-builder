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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\enums\OnConflictAction;
use sad_spirit\pg_builder\nodes\lists\SetClauseList;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing ON CONFLICT clause of INSERT statement
 *
 * @property      OnConflictAction                $action
 * @property      IndexParameters|Identifier|null $target
 * @property      SetClauseList                   $set
 * @property-read WhereOrHavingClause             $where
 */
class OnConflictClause extends GenericNode
{
    protected OnConflictAction $p_action;
    protected IndexParameters|Identifier|null $p_target = null;
    protected SetClauseList $p_set;
    protected WhereOrHavingClause $p_where;

    public function __construct(
        OnConflictAction $action,
        IndexParameters|Identifier|null $target = null,
        ?SetClauseList $set = null,
        ?ScalarExpression $condition = null
    ) {
        $this->generatePropertyNames();
        $this->setAction($action);
        $this->setTarget($target);

        $this->p_set = $set ?? new SetClauseList();
        $this->p_set->setParentNode($this);

        $this->p_where = new WhereOrHavingClause($condition);
        $this->p_where->setParentNode($this);
    }

    public function setAction(OnConflictAction $action): void
    {
        $this->p_action = $action;
    }

    /**
     * Sets the Node for conflicting constraint name / index parameters
     */
    public function setTarget(IndexParameters|Identifier|null $target): void
    {
        if (OnConflictAction::UPDATE === $this->p_action && null === $target) {
            throw new InvalidArgumentException("Target must be provided for ON CONFLICT ... DO UPDATE clause");
        }
        $this->setProperty($this->p_target, $target);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkOnConflictClause($this);
    }
}
