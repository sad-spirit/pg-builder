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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\merge\{
    MergeWhenClause,
    MergeWhenList
};
use sad_spirit\pg_builder\nodes\range\{
    FromElement,
    UpdateOrDeleteTarget
};
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * AST node representing MERGE statement
 *
 * @psalm-property MergeWhenList $when
 *
 * @property UpdateOrDeleteTarget            $relation
 * @property FromElement                     $using
 * @property ScalarExpression                $on
 * @property MergeWhenList|MergeWhenClause[] $when
 */
class Merge extends Statement
{
    /** @var UpdateOrDeleteTarget */
    protected $p_relation;
    /** @var FromElement */
    protected $p_using;
    /** @var ScalarExpression */
    protected $p_on;
    /** @var MergeWhenList */
    protected $p_when;

    public function __construct(
        UpdateOrDeleteTarget $relation,
        FromElement $using,
        ScalarExpression $on,
        ?MergeWhenList $when = null
    ) {
        parent::__construct();

        $this->p_relation = $relation;
        $this->p_relation->setParentNode($this);

        $this->p_using = $using;
        $this->p_using->setParentNode($this);

        $this->p_on = $on;
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

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkMergeStatement($this);
    }
}
