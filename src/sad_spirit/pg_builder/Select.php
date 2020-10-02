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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\lists\ExpressionList;
use sad_spirit\pg_builder\nodes\lists\FromList;
use sad_spirit\pg_builder\nodes\lists\GroupByList;
use sad_spirit\pg_builder\nodes\lists\TargetList;
use sad_spirit\pg_builder\nodes\lists\WindowList;
use sad_spirit\pg_builder\nodes\WhereOrHavingClause;

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
    public function __construct(TargetList $list, $distinct = null)
    {
        parent::__construct();

        $this->setNamedProperty('list', $list);
        $this->setDistinct($distinct);

        $this->props['from']   = new FromList();
        $this->props['where']  = new WhereOrHavingClause();
        $this->props['group']  = new GroupByList();
        $this->props['having'] = new WhereOrHavingClause();
        $this->props['window'] = new WindowList();

        $this->props['from']->setParentNode($this);
        $this->props['where']->setParentNode($this);
        $this->props['group']->setParentNode($this);
        $this->props['having']->setParentNode($this);
        $this->props['window']->setParentNode($this);
    }

    public function setDistinct($distinct)
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
        $this->setNamedProperty('distinct', !$distinct ? null : $distinct);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSelectStatement($this);
    }
}
