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

use sad_spirit\pg_builder\nodes\{
    GenericNode,
    Identifier,
    NonRecursiveNode,
    QualifiedName
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for target of INSERT statement (insert_target production in gram.y)
 *
 * Reusing RelationReference for targets of INSERT/UPDATE/DELETE is not quite correct as the latter cannot
 * have column aliases and cannot participate in JOINs
 *
 * @property-read QualifiedName   $relation
 * @property-read Identifier|null $alias
 */
class InsertTarget extends GenericNode
{
    use NonRecursiveNode;

    protected QualifiedName $p_relation;
    protected ?Identifier $p_alias = null;

    public function __construct(QualifiedName $relation, ?Identifier $alias = null)
    {
        $this->generatePropertyNames();

        $this->p_relation = $relation;
        $this->p_relation->setParentNode($this);

        if (null !== $alias) {
            $this->p_alias = $alias;
            $this->p_alias->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkInsertTarget($this);
    }
}
