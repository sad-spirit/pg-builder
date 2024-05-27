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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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

    /** @var Identifier */
    protected $p_name;
    /** @var TypeName */
    protected $p_type;
    /** @var QualifiedName|null */
    protected $p_collation = null;

    public function __construct(Identifier $colId, TypeName $type, QualifiedName $collation = null)
    {
        $this->generatePropertyNames();

        $this->p_name = $colId;
        $this->p_name->setParentNode($this);

        $this->p_type = $type;
        $this->p_type->setParentNode($this);

        if (null !== $collation) {
            $this->p_collation = $collation;
            $this->p_collation->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkColumnDefinition($this);
    }
}
