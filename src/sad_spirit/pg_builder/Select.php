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

        $this->p_from->parentNode   = $this;
        $this->p_where->parentNode  = $this;
        $this->p_group->parentNode  = $this;
        $this->p_having->parentNode = $this;
        $this->p_window->parentNode = $this;
    }

    /**
     * Sets the property corresponding to DISTINCT / DISTINCT ON clause
     */
    public function setDistinct(string|bool|ExpressionList|null $distinct): void
    {
        $distinct ??= false;
        if (is_string($distinct)) {
            $distinct = ExpressionList::createFromString($this->getParserOrFail('DISTINCT clause'), $distinct);
        }

        if (is_bool($this->p_distinct)) {
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
