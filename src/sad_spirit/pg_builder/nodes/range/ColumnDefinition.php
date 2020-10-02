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
    GenericNode,
    Identifier,
    LeafNode,
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
    use LeafNode;

    public function __construct(Identifier $colId, TypeName $type, QualifiedName $collation = null)
    {
        $this->setNamedProperty('name', $colId);
        $this->setNamedProperty('type', $type);
        $this->setNamedProperty('collation', $collation);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkColumnDefinition($this);
    }
}
