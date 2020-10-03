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
 * @property      Statement      $statement
 * @property-read Identifier     $alias
 * @property-read IdentifierList $columnAliases
 * @property      bool|null      $materialized
 */
class CommonTableExpression extends GenericNode
{
    public function __construct(
        Statement $statement,
        Identifier $alias,
        IdentifierList $columnAliases = null,
        ?bool $materialized = null
    ) {
        $this->setStatement($statement);
        $this->setNamedProperty('alias', $alias);
        $this->setNamedProperty('columnAliases', $columnAliases ?? new IdentifierList());
        $this->setMaterialized($materialized);
    }

    public function setStatement(Statement $statement)
    {
        $this->setNamedProperty('statement', $statement);
    }

    public function setMaterialized(?bool $materialized)
    {
        $this->setNamedProperty('materialized', $materialized);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCommonTableExpression($this);
    }
}
