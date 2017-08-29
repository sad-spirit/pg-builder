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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\nodes\lists\NonAssociativeList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a CASE expression (with or without argument)
 *
 * @property ScalarExpression $argument
 * @property ScalarExpression $else
 */
class CaseExpression extends NonAssociativeList implements ScalarExpression
{
    public function __construct($whenClauses, ScalarExpression $elseClause = null, ScalarExpression $argument = null)
    {
        parent::__construct($whenClauses);
        if (1 > count($this->nodes)) {
            throw new InvalidArgumentException(__CLASS__ . ': at least one WHEN clause is required');
        }
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('else', $elseClause);
    }

    public function setArgument(ScalarExpression $argument = null)
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function setElse(ScalarExpression $elseClause = null)
    {
        $this->setNamedProperty('else', $elseClause);
    }

    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof WhenExpression)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of WhenExpression, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCaseExpression($this);
    }
}
