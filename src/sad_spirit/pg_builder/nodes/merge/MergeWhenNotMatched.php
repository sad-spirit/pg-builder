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
 * Represents a "WHEN NOT MATCHED" clause of MERGE statement
 *
 * @property MergeInsert|null $action
 */
class MergeWhenNotMatched extends MergeWhenClause
{
    /** @var MergeInsert|null */
    protected $p_action;

    public function setAction(?Node $action): void
    {
        if (null !== $action && !($action instanceof MergeInsert)) {
            throw new InvalidArgumentException(sprintf(
                'Only INSERT action is possible for "WHEN NOT MATCHED" clause, object(%s) given',
                get_class($action)
            ));
        }
        $this->setProperty($this->p_action, $action);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkMergeWhenNotMatched($this);
    }
}
