<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
 * @implements ElementParseable<CommonTableExpression>
 */
class WithClause extends NonAssociativeList implements Parseable, ElementParseable
{
    use HasBothPropsAndOffsets;

    /** @var bool */
    protected $p_recursive = false;

    protected static function getAllowedElementClasses(): array
    {
        return [CommonTableExpression::class];
    }

    /**
     * WithClause constructor
     *
     * @param string|null|iterable<CommonTableExpression|string> $commonTableExpressions
     * @param bool                                               $recursive
     */
    public function __construct($commonTableExpressions = null, bool $recursive = false)
    {
        $this->generatePropertyNames();
        parent::__construct($commonTableExpressions);
        $this->setRecursive($recursive);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWithClause($this);
    }

    public function createElementFromString(string $sql): Node
    {
        return $this->getParserOrFail('a list element')->parseCommonTableExpression($sql);
    }

    public static function createFromString(Parser $parser, string $sql): self
    {
        return $parser->parseWithClause($sql);
    }

    public function merge(...$lists): void
    {
        $addRecursive = false;
        foreach ($lists as $list) {
            $addRecursive = $addRecursive || $list instanceof self && $list->recursive;
        }

        parent::merge(...$lists);

        if ($addRecursive) {
            $this->p_recursive = true;
        }
    }

    public function replace($list): void
    {
        $addRecursive = $list instanceof self && $list->recursive;

        parent::replace($list);

        if ($addRecursive) {
            $this->p_recursive = true;
        }
    }

    public function setRecursive(bool $recursive): void
    {
        $this->p_recursive = $recursive;
    }
}
