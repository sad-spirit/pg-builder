<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
            throw new InvalidArgumentException(sprintf(
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
