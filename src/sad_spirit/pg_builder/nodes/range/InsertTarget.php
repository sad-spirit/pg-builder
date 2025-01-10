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

    protected ?Identifier $p_alias = null;

    public function __construct(protected QualifiedName $p_relation, ?Identifier $alias = null)
    {
        $this->generatePropertyNames();
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
