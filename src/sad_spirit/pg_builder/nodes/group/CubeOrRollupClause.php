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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\group;

use sad_spirit\pg_builder\enums\CubeOrRollup;
use sad_spirit\pg_builder\nodes\HasBothPropsAndOffsets;
use sad_spirit\pg_builder\nodes\lists\ExpressionList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing CUBE(...) and ROLLUP(...) constructs in GROUP BY clause
 *
 * @property CubeOrRollup $type
 */
class CubeOrRollupClause extends ExpressionList implements GroupByElement
{
    use HasBothPropsAndOffsets;

    protected CubeOrRollup $p_type = CubeOrRollup::CUBE;

    public function __construct($list = null, CubeOrRollup $type = CubeOrRollup::CUBE)
    {
        $this->generatePropertyNames();
        parent::__construct($list);
        $this->setType($type);
    }

    public function setType(CubeOrRollup $type): void
    {
        $this->p_type = $type;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkCubeOrRollupClause($this);
    }
}
