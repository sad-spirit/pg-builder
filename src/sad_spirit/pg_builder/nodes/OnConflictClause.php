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

use sad_spirit\pg_builder\enums\LockingStrength;
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
 * @property      ?LockingStrength                $lockStrength
 * @property-read WhereOrHavingClause             $where
 */
class OnConflictClause extends GenericNode
{
    /** @internal Maps to `$action` magic property, use the latter instead */
    protected OnConflictAction $p_action;
    /** @internal Maps to `$target` magic property, use the latter instead */
    protected IndexParameters|Identifier|null $p_target = null;
    /** @internal Maps to `$set` magic property, use the latter instead */
    protected SetClauseList $p_set;
    /** @internal Maps to `$lockStrength` magic property, use the latter instead */
    protected ?LockingStrength $p_lockStrength;
    /** @internal Maps to `$where` magic property, use the latter instead */
    protected WhereOrHavingClause $p_where;

    public function __construct(
        OnConflictAction $action,
        IndexParameters|Identifier|null $target = null,
        ?SetClauseList $set = null,
        ?ScalarExpression $condition = null,
        ?LockingStrength $lockStrength = null
    ) {
        $this->generatePropertyNames();
        $this->setAction($action);
        $this->setTarget($target);
        $this->setLockStrength($lockStrength);

        $this->p_set = $set ?? new SetClauseList();
        $this->p_set->setParentNode($this);

        $this->p_where = new WhereOrHavingClause($condition);
        $this->p_where->setParentNode($this);
    }

    /** @internal Support method for `$action` magic property, use the property instead */
    public function setAction(OnConflictAction $action): void
    {
        $this->p_action = $action;
    }

    /**
     * Sets the Node for conflicting constraint name / index parameters
     *
     * @internal Support method for `$target` magic property, use the property instead
     */
    public function setTarget(IndexParameters|Identifier|null $target): void
    {
        if (
            (OnConflictAction::UPDATE === $this->p_action || OnConflictAction::SELECT === $this->p_action)
            && null === $target
        ) {
            throw new InvalidArgumentException(
                "Target must be provided for ON CONFLICT ... DO (UPDATE|SELECT) clause"
            );
        }
        $this->setProperty($this->p_target, $target);
    }

    /** @internal Support method for `$lockStrength` magic property, use the property instead */
    public function setLockStrength(?LockingStrength $lockStrength): void
    {
        if (OnConflictAction::SELECT !== $this->p_action && null !== $lockStrength) {
            throw new InvalidArgumentException(
                "Table locking can only be specified for ON CONFLICT ... DO SELECT clause"
            );
        }
        $this->p_lockStrength = $lockStrength;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkOnConflictClause($this);
    }
}
