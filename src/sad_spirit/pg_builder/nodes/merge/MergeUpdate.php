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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\merge;

use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\{
    GenericNode,
    MultipleSetClause,
    SingleSetClause,
    lists\SetClauseList
};

/**
 * AST node representing UPDATE action for MERGE statements
 *
 * @psalm-property SetClauseList $set
 *
 * @property SetClauseList|SingleSetClause[]|MultipleSetClause[] $set
 */
class MergeUpdate extends GenericNode
{
    /** @var SetClauseList */
    protected $p_set;

    public function __construct(SetClauseList $set)
    {
        $this->generatePropertyNames();

        $this->p_set = $set;
        $this->p_set->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkMergeUpdate($this);
    }
}
