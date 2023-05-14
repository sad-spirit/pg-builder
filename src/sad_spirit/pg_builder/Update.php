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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    MultipleSetClause,
    SingleSetClause,
    TargetElement,
    WhereOrHavingClause,
    lists\FromList,
    lists\TargetList,
    lists\SetClauseList,
    range\FromElement,
    range\UpdateOrDeleteTarget
};

/**
 * AST node representing UPDATE statement
 *
 * @psalm-property SetClauseList $set
 * @psalm-property FromList      $from
 * @psalm-property TargetList    $returning
 *
 * @property-read UpdateOrDeleteTarget                                $relation
 * @property      SetClauseList|SingleSetClause[]|MultipleSetClause[] $set
 * @property      FromList|FromElement[]                              $from
 * @property-read WhereOrHavingClause                                 $where
 * @property      TargetList|TargetElement[]                          $returning
 */
class Update extends Statement
{
    /** @var UpdateOrDeleteTarget */
    protected $p_relation;
    /** @var SetClauseList */
    protected $p_set;
    /** @var FromList */
    protected $p_from;
    /** @var WhereOrHavingClause */
    protected $p_where;
    /** @var TargetList */
    protected $p_returning;

    public function __construct(UpdateOrDeleteTarget $relation, SetClauseList $set)
    {
        parent::__construct();

        $relation->setParentNode($this);
        $this->p_relation = $relation;

        $set->setParentNode($this);
        $this->p_set = $set;

        $this->p_from      = new FromList();
        $this->p_returning = new TargetList();
        $this->p_where     = new WhereOrHavingClause();

        $this->p_from->parentNode      = $this;
        $this->p_returning->parentNode = $this;
        $this->p_where->parentNode     = $this;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkUpdateStatement($this);
    }
}
