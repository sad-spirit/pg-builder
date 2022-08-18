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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\group;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\NonRecursiveNode,
    TreeWalker
};

/**
 * AST node representing empty grouping set '()' in GROUP BY clause
 */
class EmptyGroupingSet extends GenericNode implements GroupByElement
{
    use NonRecursiveNode;

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkEmptyGroupingSet($this);
    }
}
