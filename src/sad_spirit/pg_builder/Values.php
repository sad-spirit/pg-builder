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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\lists\RowList;

/**
 * AST node representing VALUES statement
 *
 * @property RowList $rows
 */
class Values extends SelectCommon
{
    public function __construct(RowList $rows)
    {
        parent::__construct();
        $this->setNamedProperty('rows', $rows);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkValuesStatement($this);
    }
}