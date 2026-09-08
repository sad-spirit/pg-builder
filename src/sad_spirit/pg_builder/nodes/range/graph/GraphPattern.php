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

namespace sad_spirit\pg_builder\nodes\range\graph;

use sad_spirit\pg_builder\{
    ElementParseable,
    Node,
    Parseable,
    Parser,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\{
    HasBothPropsAndOffsets,
    WhereOrHavingClause,
    lists\NonAssociativeList
};

/**
 * AST node representing a query to a property graph
 *
 * The query is a list of path patterns represented here as array offsets and an optional WHERE clause
 * represented by `$where` property
 *
 * @property-read WhereOrHavingClause $where
 *
 * @extends NonAssociativeList<
 *      PathPattern,
 *      iterable<PathPattern|string>|string,
 *      PathPattern|string
 *  >
 */
class GraphPattern extends NonAssociativeList implements Parseable, ElementParseable
{
    use HasBothPropsAndOffsets;

    /** @internal Maps to `$where` magic property, use the latter instead */
    protected WhereOrHavingClause $p_where;

    public function __construct(iterable|string|null $list = null, ?WhereOrHavingClause $where = null)
    {
        $this->generatePropertyNames();
        parent::__construct($list);

        $this->p_where = $where ?? new WhereOrHavingClause();
        $this->p_where->setParentNode($this);
    }

    protected static function getAllowedElementClasses(): array
    {
        return [PathPattern::class];
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parsePathPattern($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseGraphPattern($sql);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkGraphPattern($this);
    }
}
