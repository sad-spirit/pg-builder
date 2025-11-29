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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a `WHEN MATCHED` / `WHEN NOT MATCHED BY SOURCE` clause of `MERGE` statement
 *
 * @property MergeUpdate|MergeDelete|null $action
 * @property bool                         $matchedBySource When false, represents `WHEN NOT MATCHED BY SOURCE`
 */
class MergeWhenMatched extends MergeWhenClause
{
    /** @internal Maps to `$action` magic property, use the latter instead */
    protected MergeUpdate|MergeDelete|null $p_action;
    /** @internal Maps to `$matchedBySource` magic property, use the latter instead */
    protected bool $p_matchedBySource = true;

    public function __construct(
        ?ScalarExpression $condition = null,
        ?Node $action = null,
        bool $matchedBySource = true
    ) {
        parent::__construct($condition, $action);

        $this->p_matchedBySource = $matchedBySource;
    }

    /** @internal Support method for `$action` magic property, use the property instead */
    public function setAction(?Node $action): void
    {
        if (null !== $action && !($action instanceof MergeDelete) && !($action instanceof MergeUpdate)) {
            throw new InvalidArgumentException(\sprintf(
                'Only UPDATE or DELETE action is possible for "WHEN MATCHED" / "WHEN NOT MATCHED BY SOURCE"'
                . ' clause, object(%s) given',
                $action::class
            ));
        }
        $this->setProperty($this->p_action, $action);
    }

    /** @internal Support method for `$matchedBySource` magic property, use the property instead */
    public function setMatchedBySource(bool $matchedBySource): void
    {
        $this->p_matchedBySource = $matchedBySource;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeWhenMatched($this);
    }
}
