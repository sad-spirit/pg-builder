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

use sad_spirit\pg_builder\ElementParseable,
    sad_spirit\pg_builder\NodeList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Only allows numeric indexes in arrays
 */
abstract class NonAssociativeList extends NodeList
{
    protected function normalizeElement(&$offset, &$value)
    {
        if (!is_null($offset)) {
            if (!ctype_digit((string)$offset)) {
                throw new InvalidArgumentException("Nonnegative numeric offsets expected, '{$offset}' given");
            } elseif (!is_int($offset)) {
                $offset = (int)$offset;
            }
        }

        if (is_string($value) && $this instanceof ElementParseable) {
            $value = $this->createElementFromString($value);
        }
    }
}