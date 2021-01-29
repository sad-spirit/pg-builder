<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    lists\FromList,
    lists\TargetList,
    lists\SetClauseList,
    range\UpdateOrDeleteTarget,
    WhereOrHavingClause
};

/**
 * AST node representing UPDATE statement
 *
 * @property-read UpdateOrDeleteTarget  $relation
 * @property      SetClauseList         $set
 * @property      FromList              $from
 * @property-read WhereOrHavingClause   $where
 * @property      TargetList            $returning
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

        $this->setProperty($this->p_relation, $relation);
        $this->setProperty($this->p_set, $set);
        $this->setProperty($this->p_from, new FromList());
        $this->setProperty($this->p_returning, new TargetList());
        $this->setProperty($this->p_where, new WhereOrHavingClause());
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkUpdateStatement($this);
    }
}
