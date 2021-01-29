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
    lists\ExpressionList,
    lists\FromList,
    lists\GroupByList,
    lists\TargetList,
    lists\WindowList,
    WhereOrHavingClause
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Represents a (simple) SELECT statement
 *
 * @property      TargetList          $list
 * @property      bool|ExpressionList $distinct
 * @property      FromList            $from
 * @property-read WhereOrHavingClause $where
 * @property      GroupByList         $group
 * @property-read WhereOrHavingClause $having
 * @property      WindowList          $window
 */
class Select extends SelectCommon
{
    /** @var TargetList */
    protected $p_list;
    /** @var bool|ExpressionList */
    protected $p_distinct;
    /** @var FromList */
    protected $p_from;
    /** @var WhereOrHavingClause */
    protected $p_where;
    /** @var GroupByList */
    protected $p_group;
    /** @var WhereOrHavingClause */
    protected $p_having;
    /** @var WindowList */
    protected $p_window;

    public function __construct(TargetList $list, $distinct = null)
    {
        parent::__construct();

        $this->setProperty($this->p_list, $list);
        $this->setProperty($this->p_from, new FromList());
        $this->setProperty($this->p_where, new WhereOrHavingClause());
        $this->setProperty($this->p_group, new GroupByList());
        $this->setProperty($this->p_having, new WhereOrHavingClause());
        $this->setProperty($this->p_window, new WindowList());
        $this->setDistinct($distinct);
    }

    public function setDistinct($distinct): void
    {
        if (is_string($distinct)) {
            $distinct = ExpressionList::createFromString($this->getParserOrFail('DISTINCT clause'), $distinct);
        }
        if (!is_null($distinct) && !is_bool($distinct) && !($distinct instanceof ExpressionList)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a boolean or an instance of ExpressionList, %s given',
                __METHOD__,
                is_object($distinct) ? 'object(' . get_class($distinct) . ')' : gettype($distinct)
            ));
        }
        $this->setProperty($this->p_distinct, !$distinct ? null : $distinct);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSelectStatement($this);
    }
}
