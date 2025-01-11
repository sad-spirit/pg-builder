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
    nodes\NonRecursiveNode,
    nodes\QualifiedName,
    TreeWalker
};

/**
 * AST node for relation (table or view) reference in FROM clause
 *
 * @property-read QualifiedName $name
 * @property-read bool|null     $inherit
 */
class RelationReference extends FromElement
{
    use NonRecursiveNode;

    public function __construct(protected QualifiedName $p_name, protected ?bool $p_inherit = null)
    {
        $this->generatePropertyNames();
        $this->p_name->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkRelationReference($this);
    }
}
