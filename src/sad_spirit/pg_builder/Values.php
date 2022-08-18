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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\expressions\RowExpression;
use sad_spirit\pg_builder\nodes\lists\RowList;

/**
 * AST node representing VALUES statement
 *
 * @psalm-property RowList $rows
 *
 * @property RowList|RowExpression[] $rows
 */
class Values extends SelectCommon
{
    /** @var RowList */
    protected $p_rows;

    public function __construct(RowList $rows)
    {
        parent::__construct();

        $rows->setParentNode($this);
        $this->p_rows = $rows;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkValuesStatement($this);
    }
}
