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
 * @copyright 2014-2018 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\QualifiedName,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\TreeWalker;

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
    public function __construct(
        RelationReference $relation, QualifiedName $method, ExpressionList $arguments,
        ScalarExpression $repeatable = null
    ) {
        $this->setNamedProperty('relation', $relation);
        $this->setMethod($method);
        $this->setNamedProperty('arguments', $arguments);
        $this->setRepeatable($repeatable);
    }

    public function setMethod(QualifiedName $method)
    {
        $this->setNamedProperty('method', $method);
    }

    public function setRepeatable(ScalarExpression $repeatable = null)
    {
        $this->setNamedProperty('repeatable', $repeatable);
    }

    public function setAlias(Identifier $tableAlias = null, $columnAliases = null)
    {
        $this->props['relation']->setAlias($tableAlias, $columnAliases);
    }

    public function __get($name)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            return $this->props['relation']->__get($name);
        } else {
            return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            $this->props['relation']->__set($name, $value);
        } else {
            parent::__set($name, $value);
        }
    }

    public function __isset($name)
    {
        if ('tableAlias' === $name || 'columnAliases' === $name) {
            return $this->props['relation']->__isset($name);
        } else {
            return parent::__isset($name);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkTableSample($this);
    }
}