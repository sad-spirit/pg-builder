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

namespace sad_spirit\pg_builder\nodes\merge;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Base class for Nodes representing "WHEN [NOT] MATCHED ..." clauses of MERGE statement
 *
 * `WHEN MATCHED` / `WHEN NOT MATCHED` are represented by different classes as they have different possible actions
 *
 * @property ScalarExpression|null $condition
 */
abstract class MergeWhenClause extends GenericNode
{
    /** @internal Maps to `$condition` magic property, use the latter instead */
    protected ScalarExpression|null $p_condition = null;

    public function __construct(?ScalarExpression $condition = null, ?Node $action = null)
    {
        $this->generatePropertyNames();

        $this->setCondition($condition);
        $this->setAction($action);
    }

    /** @internal Support method for `$condition` magic property, use the property instead */
    public function setCondition(?ScalarExpression $condition): void
    {
        $this->setProperty($this->p_condition, $condition);
    }

    /** @internal Support method for `$action` magic property, use the property instead */
    abstract public function setAction(?Node $action): void;
}
