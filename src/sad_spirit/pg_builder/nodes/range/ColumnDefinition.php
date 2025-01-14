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
    TypeName,
    QualifiedName
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing column definition, used in a list column aliases for functions in FROM clause
 *
 * @property-read Identifier         $name
 * @property-read TypeName           $type
 * @property-read QualifiedName|null $collation
 */
class ColumnDefinition extends GenericNode
{
    use NonRecursiveNode;

    protected Identifier $p_name;
    protected TypeName $p_type;
    protected ?QualifiedName $p_collation = null;

    public function __construct(Identifier $name, TypeName $type, ?QualifiedName $collation = null)
    {
        $this->generatePropertyNames();

        $this->p_name = $name;
        $this->p_name->setParentNode($this);

        $this->p_type = $type;
        $this->p_type->setParentNode($this);

        if (null !== $collation) {
            $this->p_collation = $collation;
            $this->p_collation->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkColumnDefinition($this);
    }
}
