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

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\nodes\SetToDefault,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\Parseable,
    sad_spirit\pg_builder\ElementParseable,
    sad_spirit\pg_builder\Parser;

/**
 * List of scalar expressions, may appear e.g. in row constructors
 *
 * In Postgres 10+ DEFAULT keyword is allowed by grammar in any expression (a_expr production),
 * however it will later raise an error except when appearing as a top-level expression
 *  - in row of VALUES clause *if* that clause is attached to INSERT
 *  - in row expression being assigned to multiple columns in UPDATE
 *
 * Therefore we don't make SetToDefault node an implementation of ScalarExpression and only allow
 * it on the top level of RowExpression. Since the latter extends ExpressionList, the knobs to
 * allow SetToDefault are defined here.
 */
class ExpressionList extends NonAssociativeList implements Parseable, ElementParseable
{
    /**
     * Whether to allow SetToDefault nodes in list
     * @var bool
     */
    protected $allowDefault = false;

    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof ScalarExpression)
            && (!$this->allowDefault || !($value instanceof SetToDefault))
        ) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of ScalarExpression'
                . ($this->allowDefault ? ' or SetToDefault': '') . ', %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public function createElementFromString($sql)
    {
        if (!($parser = $this->getParser())) {
            throw new InvalidArgumentException("Passed a string as a list element without a Parser available");
        }
        return $this->allowDefault ? $parser->parseExpressionWithDefault($sql) : $parser->parseExpression($sql);
    }

    public static function createFromString(Parser $parser, $sql)
    {
        return $parser->parseExpressionList($sql);
    }
}
