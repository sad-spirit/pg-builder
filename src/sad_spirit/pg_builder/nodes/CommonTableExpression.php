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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    Merge,
    Statement,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\{
    cte\CycleClause,
    cte\SearchClause,
    lists\IdentifierList
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

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
 * @property      SearchClause|null           $search
 * @property      CycleClause|null            $cycle
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
    /** @var SearchClause|null */
    protected $p_search;
    /** @var CycleClause|null */
    protected $p_cycle;

    public function __construct(
        Statement $statement,
        Identifier $alias,
        IdentifierList $columnAliases = null,
        ?bool $materialized = null,
        ?SearchClause $search = null,
        ?CycleClause $cycle = null
    ) {
        if ($statement instanceof Merge) {
            throw new InvalidArgumentException("MERGE not supported in WITH query");
        }

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

    public function setStatement(Statement $statement): void
    {
        if ($statement instanceof Merge) {
            throw new InvalidArgumentException("MERGE not supported in WITH query");
        }

        $this->setRequiredProperty($this->p_statement, $statement);
    }

    public function setMaterialized(?bool $materialized): void
    {
        $this->p_materialized = $materialized;
    }

    public function setSearch(?SearchClause $search): void
    {
        $this->setProperty($this->p_search, $search);
    }

    public function setCycle(?CycleClause $cycle): void
    {
        $this->setProperty($this->p_cycle, $cycle);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCommonTableExpression($this);
    }
}
