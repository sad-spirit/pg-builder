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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\nodes\lists\NonAssociativeList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\ElementParseable,
    sad_spirit\pg_builder\Parseable,
    sad_spirit\pg_builder\Parser,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing index parameters from ON CONFLICT clause
 *
 * Specifying index parameters allows Postgres to find a suitable unique index without
 * naming the constraint explicitly.
 *
 * @property-read WhereOrHavingClause $where
 */
class IndexParameters extends NonAssociativeList implements Parseable, ElementParseable
{
    public function __construct($array = null)
    {
        parent::__construct($array);
        $this->props['where'] = new WhereOrHavingClause();
        $this->props['where']->setParentNode($this);
    }

    protected function normalizeElement(&$offset, &$value)
    {
        parent::normalizeElement($offset, $value);

        if (!($value instanceof IndexElement)) {
            throw new InvalidArgumentException(sprintf(
                '%s can contain only instances of IndexElement, %s given',
                __CLASS__, is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
            ));
        }
    }

    public static function createFromString(Parser $parser, $sql)
    {
        return $parser->parseIndexParameters($sql);
    }

    public function createElementFromString($sql)
    {
        if (!($parser = $this->getParser())) {
            throw new InvalidArgumentException("Passed a string as a list element without a Parser available");
        }
        return $parser->parseIndexElement($sql);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIndexParameters($this);
    }
}