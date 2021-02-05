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

    /** @var QualifiedName */
    protected $p_name;
    /** @var bool|null */
    protected $p_inherit;

    public function __construct(QualifiedName $qualifiedName, ?bool $inheritOption = null)
    {
        $this->generatePropertyNames();

        $this->p_name = $qualifiedName;
        $this->p_name->setParentNode($this);

        $this->p_inherit = $inheritOption;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRelationReference($this);
    }
}
