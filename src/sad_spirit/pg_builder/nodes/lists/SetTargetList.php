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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\nodes\SetTargetElement,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\ElementParseable,
    sad_spirit\pg_builder\Update;

/**
 * Represents a list of SetTargetElements, used by INSERT and UPDATE statements
 *
 * NB: we don't implement Parseable here as it's difficult to deduce what format to use: INSERT or UPDATE.
 * ElementParseable is a bit easier since we know the owner element of the list. We do, however, override
 * stock normalizeArray() so the class behaves like Parseable with replace() and merge() anyway.
 */
class SetTargetList extends NonAssociativeList implements ElementParseable
{
    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof SetTargetElement)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of SetTargetElement, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public function createElementFromString($sql)
    {
        if (!($parser = $this->getParser())) {
            throw new InvalidArgumentException("Passed a string as a list element without a Parser available");
        }
        if ($this->parentNode instanceof Update) {
            return $parser->parseSingleSetClause($sql);
        } else {
            return $parser->parseSetTargetElement($sql);
        }
    }

    protected function normalizeArray(&$array, $method)
    {
        if (is_string($array)) {
            if (!($parser = $this->getParser())) {
                throw new InvalidArgumentException("Passed a string to method '{$method}' without a Parser available");
            }
            if ($this->parentNode instanceof Update) {
                $array = $parser->parseSetClause($array);
            } else {
                $array = $parser->parseInsertTargetList($array);
            }
        }
        parent::normalizeArray($array, $method);
    }
}