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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\{
    nodes\Identifier,
    nodes\QualifiedName,
    TreeWalker
};

/**
 * AST node for target of UPDATE or DELETE statement, corresponding to relation_expr_opt_alias in gram.y
 *
 * @property-read bool|null $inherit
 */
class UpdateOrDeleteTarget extends InsertTarget
{
    /** @var bool|null */
    protected $p_inherit;

    public function __construct(QualifiedName $relation, Identifier $alias = null, ?bool $inheritOption = null)
    {
        $this->generatePropertyNames();
        parent::__construct($relation, $alias);
        $this->p_inherit = $inheritOption;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkUpdateOrDeleteTarget($this);
    }
}
