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
 * @psalm-property ExpressionList $arguments
 *
 * @property-read RelationReference                 $relation
 * @property      QualifiedName                     $method
 * @property      ExpressionList|ScalarExpression[] $arguments
 * @property      ScalarExpression|null             $repeatable
 */
class TableSample extends FromElement
{
    /** @var RelationReference */
    protected $p_relation;
    /** @var QualifiedName */
    protected $p_method;
    /** @var ExpressionList */
    protected $p_arguments;
    /** @var ScalarExpression|null */
    protected $p_repeatable = null;

    public function __construct(
        RelationReference $relation,
        QualifiedName $method,
        ExpressionList $arguments,
        ScalarExpression $repeatable = null
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

    public function setRepeatable(ScalarExpression $repeatable = null): void
    {
        $this->setProperty($this->p_repeatable, $repeatable);
    }

    public function setAlias(Identifier $tableAlias = null, NodeList $columnAliases = null): void
    {
        $this->p_relation->setAlias($tableAlias, $columnAliases);
    }

    public function __get($name)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            return $this->p_relation->__get($name);
        } else {
            return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            $this->p_relation->__set($name, $value);
        } else {
            parent::__set($name, $value);
        }
    }

    public function __isset($name)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            return true;
        } else {
            return parent::__isset($name);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkTableSample($this);
    }
}
