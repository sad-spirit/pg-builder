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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\ElementParseable,
    sad_spirit\pg_builder\Parseable,
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\nodes\MultipleSetClause,
    sad_spirit\pg_builder\nodes\SingleSetClause,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Represents SET clause of UPDATE statement
 */
class SetClauseList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);
        
        if (!($value instanceof SingleSetClause) && !($value instanceof MultipleSetClause)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of SingleSetClause or MultipleSetClause, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
            
        }
    }

    public function createElementFromString($sql)
    {
        if (!($parser = $this->getParser())) {
            throw new InvalidArgumentException("Passed a string as a list element without a Parser available");
        }
        return $parser->parseSetClause($sql);
    }

    public static function createFromString(Parser $parser, $sql)
    {
        return $parser->parseSetClauseList($sql);
    }
}