<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\{
    Node,
    nodes\TargetElement,
    nodes\Star,
    Parseable,
    ElementParseable,
    Parser
};

/**
 * List of elements in SELECT's target list
 *
 * @extends NonAssociativeList<
 *     TargetElement|Star,
 *     iterable<TargetElement|Star|string>|string,
 *     TargetElement|Star|string
 * >
 * @implements ElementParseable<TargetElement|Star>
 */
class TargetList extends NonAssociativeList implements Parseable, ElementParseable
{
    protected static function getAllowedElementClasses(): array
    {
        return [
            TargetElement::class,
            Star::class
        ];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseTargetElement($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseTargetList($sql);
    }
}
