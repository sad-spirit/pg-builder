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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    Node,
    ElementParseable,
    Parseable,
    Parser,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * AST node representing index parameters from ON CONFLICT clause
 *
 * Specifying index parameters allows Postgres to find a suitable unique index without
 * naming the constraint explicitly.
 *
 * @property-read WhereOrHavingClause $where
 * @extends NonAssociativeList<IndexElement, iterable<IndexElement|string>|string, IndexElement|string>
 * @implements ElementParseable<IndexElement>
 */
class IndexParameters extends NonAssociativeList implements Parseable, ElementParseable
{
    use HasBothPropsAndOffsets;

    protected WhereOrHavingClause $p_where;

    protected static function getAllowedElementClasses(): array
    {
        return [IndexElement::class];
    }

    public function __construct($list = null)
    {
        $this->generatePropertyNames();
        parent::__construct($list);
        $this->p_where = new WhereOrHavingClause();
        $this->p_where->parentNode = \WeakReference::create($this);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseIndexParameters($sql);
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseIndexElement($sql);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkIndexParameters($this);
    }
}
