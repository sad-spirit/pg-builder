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
    OnConflictClause,
    ReturningClause,
    lists\SetTargetList,
    range\InsertTarget
};
use sad_spirit\pg_builder\enums\InsertOverriding;

/**
 * AST node representing INSERT statement
 *
 * @property-read InsertTarget          $relation
 * @property      SetTargetList         $cols
 * @property      SelectCommon|null     $values
 * @property      InsertOverriding|null $overriding
 * @property      OnConflictClause|null $onConflict
 * @property      ReturningClause       $returning
 */
class Insert extends Statement
{
    protected InsertTarget $p_relation;
    protected SetTargetList $p_cols;
    protected ?SelectCommon $p_values = null;
    protected ?InsertOverriding $p_overriding = null;
    protected ?OnConflictClause $p_onConflict = null;
    protected ReturningClause $p_returning;

    public function __construct(InsertTarget $relation)
    {
        parent::__construct();

        $relation->setParentNode($this);
        $this->p_relation = $relation;

        $this->p_cols      = new SetTargetList();
        $this->p_returning = new ReturningClause();

        $this->p_cols->parentNode      = \WeakReference::create($this);
        $this->p_returning->parentNode = \WeakReference::create($this);
    }

    public function setValues(?SelectCommon $values): void
    {
        $this->setProperty($this->p_values, $values);
    }

    /**
     * Sets the Node representing 'ON CONFLICT' clause
     */
    public function setOnConflict(string|OnConflictClause|null $onConflict): void
    {
        if (\is_string($onConflict)) {
            $onConflict = $this->getParserOrFail('ON CONFLICT clause')->parseOnConflict($onConflict);
        }
        $this->setProperty($this->p_onConflict, $onConflict);
    }

    public function setOverriding(?InsertOverriding $overriding): void
    {
        $this->p_overriding = $overriding;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkInsertStatement($this);
    }
}
