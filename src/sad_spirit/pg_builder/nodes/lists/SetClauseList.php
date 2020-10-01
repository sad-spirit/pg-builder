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

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\{
    ElementParseable,
    Node,
    Parseable,
    Parser,
    nodes\MultipleSetClause,
    nodes\SingleSetClause,
    exceptions\InvalidArgumentException
};

/**
 * Represents SET clause of UPDATE statement
 */
class SetClauseList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [
            SingleSetClause::class,
            MultipleSetClause::class
        ];
    }

    public function createElementFromString(string $sql): Node
    {
        if (!($parser = $this->getParser())) {
            throw new InvalidArgumentException("Passed a string as a list element without a Parser available");
        }
        return $parser->parseSetClause($sql);
    }

    public static function createFromString(Parser $parser, string $sql): Node
    {
        return $parser->parseSetClauseList($sql);
    }
}
