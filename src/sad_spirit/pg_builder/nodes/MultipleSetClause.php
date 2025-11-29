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

use sad_spirit\pg_builder\nodes\lists\SetTargetList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a (column_name, ...) = (sub-select|row-expression) construct in SET clause of UPDATE statement
 *
 * Note that we allow any scalar expression as $value here (as does set_clause production in Postgres 10+ grammar),
 * however Parser will raise an error for anything that is not either a subselect or a row expression,
 * just like Postgres itself does.
 *
 * @property SetTargetList    $columns
 * @property ScalarExpression $value
 */
class MultipleSetClause extends GenericNode
{
    /** @internal Maps to `$columns` magic property, use the latter instead */
    protected SetTargetList $p_columns;
    /** @internal Maps to `$value` magic property, use the latter instead */
    protected ScalarExpression $p_value;

    public function __construct(SetTargetList $columns, ScalarExpression $value)
    {
        $this->generatePropertyNames();

        $this->p_columns = $columns;
        $this->p_columns->setParentNode($this);

        $this->p_value = $value;
        $this->p_value->setParentNode($this);
    }

    /** @internal Support method for `$value` magic property, use the property instead */
    public function setValue(ScalarExpression $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMultipleSetClause($this);
    }
}
