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
    ScalarExpression,
    merge\MergeWhenList,
    range\FromElement,
    range\UpdateOrDeleteTarget
};

/**
 * AST node representing MERGE statement
 *
 * @property UpdateOrDeleteTarget $relation
 * @property FromElement          $using
 * @property ScalarExpression     $on
 * @property MergeWhenList        $when
 */
class Merge extends Statement
{
    protected MergeWhenList $p_when;

    public function __construct(
        protected UpdateOrDeleteTarget $p_relation,
        protected FromElement $p_using,
        protected ScalarExpression $p_on,
        ?MergeWhenList $when = null
    ) {
        parent::__construct();
        $this->p_relation->setParentNode($this);
        $this->p_using->setParentNode($this);
        $this->p_on->setParentNode($this);

        $this->p_when = $when ?? new MergeWhenList();
        $this->p_when->setParentNode($this);
    }

    public function setRelation(UpdateOrDeleteTarget $relation): void
    {
        $this->setRequiredProperty($this->p_relation, $relation);
    }

    public function setUsing(FromElement $using): void
    {
        $this->setRequiredProperty($this->p_using, $using);
    }

    public function setOn(ScalarExpression $on): void
    {
        $this->setRequiredProperty($this->p_on, $on);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeStatement($this);
    }
}
