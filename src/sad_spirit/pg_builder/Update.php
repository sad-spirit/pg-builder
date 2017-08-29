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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\lists\FromList,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\SetClauseList,
    sad_spirit\pg_builder\nodes\range\UpdateOrDeleteTarget,
    sad_spirit\pg_builder\nodes\WhereOrHavingClause;

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
    public function __construct(UpdateOrDeleteTarget $relation, SetClauseList $set)
    {
        parent::__construct();

        $this->setNamedProperty('relation', $relation);
        $this->setNamedProperty('set', $set);
        $this->props['from']      = new FromList();
        $this->props['returning'] = new TargetList();
        $this->props['where']     = new WhereOrHavingClause();

        $this->props['from']->setParentNode($this);
        $this->props['returning']->setParentNode($this);
        $this->props['where']->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkUpdateStatement($this);
    }
}
