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
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a "WHEN MATCHED" clause of MERGE statement
 *
 * @property MergeUpdate|MergeDelete|null $action
 */
class MergeWhenMatched extends MergeWhenClause
{
    protected MergeUpdate|MergeDelete|null $p_action;

    public function setAction(?Node $action): void
    {
        if (null !== $action && !($action instanceof MergeDelete) && !($action instanceof MergeUpdate)) {
            throw new InvalidArgumentException(\sprintf(
                'Only UPDATE or DELETE action is possible for "WHEN MATCHED" clause, object(%s) given',
                $action::class
            ));
        }
        $this->setProperty($this->p_action, $action);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeWhenMatched($this);
    }
}
