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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    WhereOrHavingClause,
    group\GroupByClause,
    lists\ExpressionList,
    lists\FromList,
    lists\TargetList,
    lists\WindowList
};

/**
 * Represents a (simple) SELECT statement
 *
 * @property      TargetList          $list
 * @property      bool|ExpressionList $distinct
 * @property      FromList            $from
 * @property-read WhereOrHavingClause $where
 * @property      GroupByClause       $group
 * @property-read WhereOrHavingClause $having
 * @property      WindowList          $window
 */
class Select extends SelectCommon
{
    protected TargetList $p_list;
    protected bool|ExpressionList $p_distinct = false;
    protected FromList $p_from;
    protected WhereOrHavingClause $p_where;
    protected GroupByClause $p_group;
    protected WhereOrHavingClause $p_having;
    protected WindowList $p_window;

    public function __construct(TargetList $list, bool|ExpressionList $distinct = false)
    {
        parent::__construct();

        $list->setParentNode($this);
        $this->p_list = $list;

        $this->setDistinct($distinct);

        $this->p_from   = new FromList();
        $this->p_where  = new WhereOrHavingClause();
        $this->p_group  = new GroupByClause();
        $this->p_having = new WhereOrHavingClause();
        $this->p_window = new WindowList();

        $this->p_from->parentNode   = \WeakReference::create($this);
        $this->p_where->parentNode  = \WeakReference::create($this);
        $this->p_group->parentNode  = \WeakReference::create($this);
        $this->p_having->parentNode = \WeakReference::create($this);
        $this->p_window->parentNode = \WeakReference::create($this);
    }

    /**
     * Sets the property corresponding to DISTINCT / DISTINCT ON clause
     */
    public function setDistinct(string|bool|ExpressionList|null $distinct): void
    {
        $distinct ??= false;
        if (\is_string($distinct)) {
            $distinct = ExpressionList::createFromString($this->getParserOrFail('DISTINCT clause'), $distinct);
        }

        if (\is_bool($this->p_distinct)) {
            if ($distinct instanceof ExpressionList) {
                $distinct->setParentNode($this);
            }
            $this->p_distinct = $distinct;
        } elseif ($distinct instanceof ExpressionList) {
            $this->setRequiredProperty($this->p_distinct, $distinct);
        } else {
            $this->p_distinct->setParentNode(null);
            $this->p_distinct = $distinct;
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSelectStatement($this);
    }
}
