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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    Statement,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\{
    cte\CycleClause,
    cte\SearchClause,
    lists\IdentifierList
};

/**
 * AST node representing a CTE
 *
 * Quite similar to range\Subselect, but any statement is allowed here, not only
 * SELECT
 *
 * @property      Statement         $statement
 * @property-read Identifier        $alias
 * @property-read IdentifierList    $columnAliases
 * @property      bool|null         $materialized
 * @property      SearchClause|null $search
 * @property      CycleClause|null  $cycle
 */
class CommonTableExpression extends GenericNode
{
    /** @internal Maps to `$statement` magic property, use the latter instead */
    protected Statement $p_statement;
    /** @internal Maps to `$alias` magic property, use the latter instead */
    protected Identifier $p_alias;
    /** @internal Maps to `$columnAliases` magic property, use the latter instead */
    protected IdentifierList $p_columnAliases;
    /** @internal Maps to `$materialized` magic property, use the latter instead */
    protected ?bool $p_materialized = null;
    /** @internal Maps to `$search` magic property, use the latter instead */
    protected ?SearchClause $p_search = null;
    /** @internal Maps to `$cycle` magic property, use the latter instead */
    protected ?CycleClause $p_cycle = null;

    public function __construct(
        Statement $statement,
        Identifier $alias,
        ?IdentifierList $columnAliases = null,
        ?bool $materialized = null,
        ?SearchClause $search = null,
        ?CycleClause $cycle = null
    ) {
        $this->generatePropertyNames();

        $this->p_statement = $statement;
        $this->p_statement->setParentNode($this);

        $this->p_alias = $alias;
        $this->p_alias->setParentNode($this);

        $this->p_columnAliases = $columnAliases ?? new IdentifierList();
        $this->p_columnAliases->setParentNode($this);

        $this->p_materialized = $materialized;

        if (null !== $search) {
            $this->p_search = $search;
            $this->p_search->setParentNode($this);
        }
        if (null !== $cycle) {
            $this->p_cycle = $cycle;
            $this->p_cycle->setParentNode($this);
        }
    }

    /** @internal Support method for `$statement` magic property, use the property instead */
    public function setStatement(Statement $statement): void
    {
        $this->setRequiredProperty($this->p_statement, $statement);
    }

    /** @internal Support method for `$materialized` magic property, use the property instead */
    public function setMaterialized(?bool $materialized): void
    {
        $this->p_materialized = $materialized;
    }

    /** @internal Support method for `$search` magic property, use the property instead */
    public function setSearch(?SearchClause $search): void
    {
        $this->setProperty($this->p_search, $search);
    }

    /** @internal Support method for `$cycle` magic property, use the property instead */
    public function setCycle(?CycleClause $cycle): void
    {
        $this->setProperty($this->p_cycle, $cycle);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkCommonTableExpression($this);
    }
}
