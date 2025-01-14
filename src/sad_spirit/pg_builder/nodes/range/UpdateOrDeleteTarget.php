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
    protected ?bool $p_inherit;

    public function __construct(QualifiedName $relation, ?Identifier $alias = null, ?bool $inherit = null)
    {
        $this->generatePropertyNames();

        parent::__construct($relation, $alias);

        $this->p_inherit = $inherit;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkUpdateOrDeleteTarget($this);
    }
}
