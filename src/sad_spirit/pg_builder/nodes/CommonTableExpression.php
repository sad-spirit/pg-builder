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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\nodes\lists\IdentifierList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a CTE
 *
 * Quite similar to range\Subselect, but any statement is allowed here, not only
 * SELECT
 *
 * @psalm-property-read IdentifierList $columnAliases
 *
 * @property      Statement                   $statement
 * @property-read Identifier                  $alias
 * @property-read IdentifierList|Identifier[] $columnAliases
 * @property      bool|null                   $materialized
 */
class CommonTableExpression extends GenericNode
{
    /** @var Statement */
    protected $p_statement;
    /** @var Identifier */
    protected $p_alias;
    /** @var IdentifierList */
    protected $p_columnAliases;
    /** @var bool|null */
    protected $p_materialized;

    public function __construct(
        Statement $statement,
        Identifier $alias,
        IdentifierList $columnAliases = null,
        ?bool $materialized = null
    ) {
        $this->generatePropertyNames();
        $this->p_statement = $statement;
        $this->p_statement->setParentNode($this);

        $this->p_alias = $alias;
        $this->p_alias->setParentNode($this);

        $this->p_columnAliases = $columnAliases ?? new IdentifierList();
        $this->p_columnAliases->setParentNode($this);

        $this->p_materialized = $materialized;
    }

    public function setStatement(Statement $statement): void
    {
        $this->setRequiredProperty($this->p_statement, $statement);
    }

    public function setMaterialized(?bool $materialized): void
    {
        $this->p_materialized = $materialized;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCommonTableExpression($this);
    }
}
