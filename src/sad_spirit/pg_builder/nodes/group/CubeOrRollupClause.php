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

namespace sad_spirit\pg_builder\nodes\group;

use sad_spirit\pg_builder\nodes\lists\ExpressionList;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing CUBE(...) and ROLLUP(...) constructs in GROUP BY clause
 *
 * @property string $type
 */
class CubeOrRollupClause extends ExpressionList implements GroupByElement
{
    public function __construct($list = null, $type = 'cube')
    {
        parent::__construct($list);
        $this->setType($type);
    }

    public function setType($type)
    {
        if (!in_array($type, ['cube', 'rollup'], true)) {
            throw new InvalidArgumentException("Unknown grouping set type '{$type}'");
        }
        $this->props['type'] = $type;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCubeOrRollupClause($this);
    }
}
