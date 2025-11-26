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
    TreeWalker,
    Parseable,
    ElementParseable,
    Parser
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * WITH clause containing common table expressions
 *
 * @property bool $recursive
 * @extends NonAssociativeList<
 *     CommonTableExpression,
 *     iterable<CommonTableExpression|string>|string,
 *     CommonTableExpression|string
 * >
 */
class WithClause extends NonAssociativeList implements Parseable, ElementParseable
{
    use HasBothPropsAndOffsets;

    protected bool $p_recursive = false;

    protected static function getAllowedElementClasses(): array
    {
        return [CommonTableExpression::class];
    }

    /**
     * WithClause constructor
     *
     * @param string|null|iterable<CommonTableExpression|string> $commonTableExpressions
     */
    public function __construct(iterable|string|null $commonTableExpressions = null, bool $recursive = false)
    {
        $this->generatePropertyNames();
        parent::__construct($commonTableExpressions);
        $this->setRecursive($recursive);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkWithClause($this);
    }

    public function createElementFromString(string $sql): CommonTableExpression
    {
        return $this->getParserOrFail('a list element')->parseCommonTableExpression($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseWithClause($sql);
    }

    public function merge(...$lists): void
    {
        foreach ($lists as &$list) {
            if (\is_string($list)) {
                $list = self::createFromString($this->getParserOrFail("an argument to 'merge'"), $list);
            }
            if ($list instanceof self && $list->recursive) {
                $this->p_recursive = true;
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
        $this->p_recursive = $list instanceof self && $list->recursive;

        parent::replace($list);
    }

    public function setRecursive(bool $recursive): void
    {
        $this->p_recursive = $recursive;
    }
}
