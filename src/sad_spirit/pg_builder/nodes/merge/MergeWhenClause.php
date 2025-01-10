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

namespace sad_spirit\pg_builder\nodes\merge;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Base class for Nodes representing "WHEN [NOT] MATCHED ..." clauses of MERGE statement
 *
 * "WHEN MATCHED" / "WHEN NOT MATCHED" are represented by different classes as they have different possible actions
 *
 * @property ScalarExpression|null $condition
 */
abstract class MergeWhenClause extends GenericNode
{
    protected ScalarExpression|null $p_condition = null;

    public function __construct(?ScalarExpression $condition = null, ?Node $action = null)
    {
        $this->generatePropertyNames();

        $this->setCondition($condition);
        $this->setAction($action);
    }

    public function setCondition(?ScalarExpression $condition): void
    {
        $this->setProperty($this->p_condition, $condition);
    }

    abstract public function setAction(?Node $action): void;
}
