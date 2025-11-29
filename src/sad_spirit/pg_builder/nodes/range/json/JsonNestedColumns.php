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

namespace sad_spirit\pg_builder\nodes\range\json;

use sad_spirit\pg_builder\nodes\expressions\StringConstant;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for NESTED column definitions in json_table() clause
 *
 * @property StringConstant           $path
 * @property Identifier|null          $pathName
 * @property JsonColumnDefinitionList $columns
 */
class JsonNestedColumns extends GenericNode implements JsonColumnDefinition
{
    /** @internal Maps to `$pathName` magic property, use the latter instead */
    protected ?Identifier $p_pathName = null;
    /** @internal Maps to `$columns` magic property, use the latter instead */
    protected JsonColumnDefinitionList $p_columns;
    /** @internal Maps to `$path` magic property, use the latter instead */
    protected StringConstant $p_path;

    public function __construct(
        StringConstant $path,
        ?Identifier $pathName = null,
        ?JsonColumnDefinitionList $columns = null
    ) {
        $this->generatePropertyNames();

        $this->p_path = $path;
        $this->p_path->setParentNode($this);

        if (null !== $pathName) {
            $this->p_pathName = $pathName;
            $this->p_pathName->setParentNode($this);
        }

        $this->p_columns = $columns ?? new JsonColumnDefinitionList();
        $this->p_columns->setParentNode($this);
    }

    /** @internal Support method for `$pathName` magic property, use the property instead */
    public function setPathName(?Identifier $pathName): void
    {
        $this->setProperty($this->p_pathName, $pathName);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonNestedColumns($this);
    }
}
