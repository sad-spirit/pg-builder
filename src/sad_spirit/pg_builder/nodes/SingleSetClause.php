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

use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a single "column_name = expression" clause of UPDATE statement
 *
 * @property-read SetTargetElement              $column
 * @property      ScalarExpression|SetToDefault $value
 */
class SingleSetClause extends GenericNode
{
    protected SetTargetElement $p_column;
    protected ScalarExpression|SetToDefault $p_value;

    public function __construct(SetTargetElement $column, ScalarExpression|SetToDefault $value)
    {
        $this->generatePropertyNames();

        $this->p_column = $column;
        $this->p_column->setParentNode($this);

        $this->p_value = $value;
        $this->p_value->setParentNode($this);
    }

    /**
     * Sets the Node representing a new value for the column
     */
    public function setValue(ScalarExpression|SetToDefault $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSingleSetClause($this);
    }
}
