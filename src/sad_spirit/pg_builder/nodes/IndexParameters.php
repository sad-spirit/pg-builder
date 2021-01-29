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
 */
class IndexParameters extends NonAssociativeList implements Parseable, ElementParseable
{
    use HasBothPropsAndOffsets;

    /** @var WhereOrHavingClause */
    protected $p_where;

    protected static function getAllowedElementClasses(): array
    {
        return [IndexElement::class];
    }

    public function __construct($list = null)
    {
        $this->generatePropertyNames();
        parent::__construct($list);
        $this->p_where = new WhereOrHavingClause();
        $this->p_where->parentNode = $this;
    }

    public static function createFromString(Parser $parser, string $sql): Node
    {
        return $parser->parseIndexParameters($sql);
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseIndexElement($sql);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIndexParameters($this);
    }
}
