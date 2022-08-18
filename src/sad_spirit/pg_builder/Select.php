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

use sad_spirit\pg_builder\nodes\{
    group\GroupByClause,
    group\GroupByElement,
    lists\ExpressionList,
    lists\FromList,
    lists\TargetList,
    lists\WindowList,
    range\FromElement,
    ScalarExpression,
    TargetElement,
    WhereOrHavingClause,
    WindowDefinition
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Represents a (simple) SELECT statement
 *
 * @psalm-property TargetList          $list
 * @psalm-property bool|ExpressionList $distinct
 * @psalm-property FromList            $from
 * @psalm-property GroupByClause       $group
 * @psalm-property WindowList          $window
 *
 * @property      TargetList|TargetElement[]                        $list
 * @property      bool|ExpressionList|ScalarExpression[]            $distinct
 * @property      FromList|FromElement[]                            $from
 * @property-read WhereOrHavingClause                               $where
 * @property      GroupByClause|GroupByElement[]|ScalarExpression[] $group
 * @property-read WhereOrHavingClause                               $having
 * @property      WindowList|WindowDefinition[]                     $window
 */
class Select extends SelectCommon
{
    /** @var TargetList */
    protected $p_list;
    /** @var bool|ExpressionList */
    protected $p_distinct = false;
    /** @var FromList */
    protected $p_from;
    /** @var WhereOrHavingClause */
    protected $p_where;
    /** @var GroupByClause */
    protected $p_group;
    /** @var WhereOrHavingClause */
    protected $p_having;
    /** @var WindowList */
    protected $p_window;

    /**
     * Select constructor
     *
     * @param TargetList          $list
     * @param bool|ExpressionList $distinct
     */
    public function __construct(TargetList $list, $distinct = false)
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
     *
     * @param string|bool|ExpressionList|null $distinct
     */
    public function setDistinct($distinct): void
    {
        $distinct = $distinct ?? false;
        if (is_string($distinct)) {
            $distinct = ExpressionList::createFromString($this->getParserOrFail('DISTINCT clause'), $distinct);
        }
        if (!is_bool($distinct) && !$distinct instanceof ExpressionList) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a boolean or an instance of ExpressionList, %s given',
                __METHOD__,
                is_object($distinct) ? 'object(' . get_class($distinct) . ')' : gettype($distinct)
            ));
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

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSelectStatement($this);
    }
}
