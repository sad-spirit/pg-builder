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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    lists\FromList,
    lists\TargetList,
    range\FromElement,
    range\UpdateOrDeleteTarget,
    TargetElement,
    WhereOrHavingClause
};

/**
 * AST node representing DELETE statement
 *
 * @psalm-property FromList   $using
 * @psalm-property TargetList $returning
 *
 * @property-read UpdateOrDeleteTarget       $relation
 * @property      FromList|FromElement[]     $using
 * @property-read WhereOrHavingClause        $where
 * @property      TargetList|TargetElement[] $returning
 */
class Delete extends Statement
{
    /** @var UpdateOrDeleteTarget */
    protected $p_relation;
    /** @var FromList */
    protected $p_using;
    /** @var WhereOrHavingClause */
    protected $p_where;
    /** @var TargetList */
    protected $p_returning;

    public function __construct(UpdateOrDeleteTarget $relation)
    {
        parent::__construct();

        $relation->setParentNode($this);
        $this->p_relation = $relation;

        $this->p_using     = new FromList();
        $this->p_returning = new TargetList();
        $this->p_where     = new WhereOrHavingClause();

        $this->p_using->parentNode     = $this;
        $this->p_returning->parentNode = $this;
        $this->p_where->parentNode     = $this;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkDeleteStatement($this);
    }
}
