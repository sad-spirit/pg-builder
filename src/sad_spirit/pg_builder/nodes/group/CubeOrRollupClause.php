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
