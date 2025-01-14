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
    Identifier,
    QualifiedName,
    ScalarExpression,
    lists\ExpressionList
};
use sad_spirit\pg_builder\NodeList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for TABLESAMPLE clause in FROM list
 *
 * @property-read RelationReference     $relation
 * @property      QualifiedName         $method
 * @property      ExpressionList        $arguments
 * @property      ScalarExpression|null $repeatable
 */
class TableSample extends FromElement
{
    protected RelationReference $p_relation;
    protected QualifiedName $p_method;
    protected ExpressionList $p_arguments;
    protected ?ScalarExpression $p_repeatable = null;

    public function __construct(
        RelationReference $relation,
        QualifiedName $method,
        ExpressionList $arguments,
        ?ScalarExpression $repeatable = null
    ) {
        $this->generatePropertyNames();

        $this->p_relation = $relation;
        $this->p_relation->setParentNode($this);

        $this->p_method = $method;
        $this->p_method->setParentNode($this);

        $this->p_arguments = $arguments;
        $this->p_arguments->setParentNode($this);

        if (null !== $repeatable) {
            $this->p_repeatable = $repeatable;
            $this->p_repeatable->setParentNode($this);
        }
    }

    public function setMethod(QualifiedName $method): void
    {
        $this->setRequiredProperty($this->p_method, $method);
    }

    public function setRepeatable(?ScalarExpression $repeatable): void
    {
        $this->setProperty($this->p_repeatable, $repeatable);
    }

    public function setAlias(?Identifier $tableAlias, ?NodeList $columnAliases = null): void
    {
        $this->p_relation->setAlias($tableAlias, $columnAliases);
    }

    public function __get(string $name)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            return $this->p_relation->__get($name);
        } else {
            return parent::__get($name);
        }
    }

    public function __set(string $name, $value)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            $this->p_relation->__set($name, $value);
        } else {
            parent::__set($name, $value);
        }
    }

    public function __isset(string $name)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkTableSample($this);
    }
}
