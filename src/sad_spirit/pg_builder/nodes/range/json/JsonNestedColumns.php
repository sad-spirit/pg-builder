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

namespace sad_spirit\pg_builder\nodes\range\json;

use sad_spirit\pg_builder\nodes\expressions\StringConstant;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for NESTED column definitions in json_table() clause
 *
 * @psalm-property JsonColumnDefinitionList $columns
 *
 * @property StringConstant                                  $path
 * @property Identifier|null                                 $pathName
 * @property JsonColumnDefinitionList|JsonColumnDefinition[] $columns
 */
class JsonNestedColumns extends GenericNode implements JsonColumnDefinition
{
    /** @var StringConstant */
    protected $p_path;
    /** @var Identifier|null */
    protected $p_pathName = null;
    /** @var JsonColumnDefinitionList */
    protected $p_columns;

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

    public function setPathName(?Identifier $pathName): void
    {
        $this->setProperty($this->p_pathName, $pathName);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonNestedColumns($this);
    }
}
