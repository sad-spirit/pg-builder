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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for target of UPDATE or DELETE statement, corresponding to relation_expr_opt_alias in gram.y
 *
 * @property-read bool|null $inherit
 */
class UpdateOrDeleteTarget extends InsertTarget
{
    public function __construct(QualifiedName $relation, Identifier $alias = null, $inheritOption = null)
    {
        parent::__construct($relation, $alias);
        $this->props['inherit'] = is_null($inheritOption) ? null : (bool)$inheritOption;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkUpdateOrDeleteTarget($this);
    }
}