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

namespace sad_spirit\pg_builder\nodes\group;

use sad_spirit\pg_builder\nodes\HasBothPropsAndOffsets;
use sad_spirit\pg_builder\nodes\lists\GroupByList;
use sad_spirit\pg_builder\Parseable;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Outermost list of elements appearing in GROUP BY clause with a possible DISTINCT clause
 *
 * The list can contain either expressions or special constructs like CUBE(), ROLLUP() and GROUPING SETS()
 *
 * @property bool $distinct
 */
class GroupByClause extends GroupByList implements Parseable
{
    use HasBothPropsAndOffsets;

    public function __construct($list = null, protected bool $p_distinct = false)
    {
        $this->generatePropertyNames();
        parent::__construct($list);
    }

    public function setDistinct(bool $distinct): void
    {
        $this->p_distinct = $distinct;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkGroupByClause($this);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseGroupByClause($sql);
    }

    public function merge(...$lists): void
    {
        foreach ($lists as &$list) {
            if (\is_string($list)) {
                $list = self::createFromString($this->getParserOrFail("an argument to 'merge'"), $list);
            }
            if ($list instanceof self && $list->distinct) {
                $this->p_distinct = true;
                break;
            }
        }
        unset($list);

        parent::merge(...$lists);
    }

    public function replace($list): void
    {
        if (\is_string($list)) {
            $list = self::createFromString($this->getParserOrFail("an argument to 'replace'"), $list);
        }
        $this->p_distinct = $list instanceof self && $list->distinct;

        parent::replace($list);
    }
}
