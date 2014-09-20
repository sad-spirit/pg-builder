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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\Statement,
    sad_spirit\pg_builder\nodes\lists\IdentifierList,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a CTE
 *
 * Quite similar to range\Subselect, but any statement is allowed here, not only
 * SELECT
 *
 * @property-read Statement      $statement
 * @property-read Identifier     $alias
 * @property-read IdentifierList $columnAliases
 */
class CommonTableExpression extends Node
{
    public function __construct(Statement $statement, Identifier $alias, IdentifierList $columnAliases = null)
    {
        $this->setNamedProperty('statement', $statement);
        $this->setNamedProperty('alias', $alias);
        $this->setNamedProperty('columnAliases', $columnAliases ?: new IdentifierList());
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCommonTableExpression($this);
    }
}