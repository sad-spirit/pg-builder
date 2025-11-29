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

namespace sad_spirit\pg_builder\nodes\cte;

use sad_spirit\pg_builder\nodes\{
    GenericNode,
    Identifier,
    NonRecursiveNode,
    lists\IdentifierList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * SEARCH BREADTH / DEPTH FIRST clause for Common Table Expressions
 *
 * @property bool           $breadthFirst
 * @property IdentifierList $trackColumns
 * @property Identifier     $sequenceColumn
 */
class SearchClause extends GenericNode
{
    use NonRecursiveNode;

    /** @internal Maps to `$breadthFirst` magic property, use the latter instead */
    protected bool $p_breadthFirst;
    /** @internal Maps to `$trackColumns` magic property, use the latter instead */
    protected ?IdentifierList $p_trackColumns = null;
    /** @internal Maps to `$sequenceColumn` magic property, use the latter instead */
    protected ?Identifier $p_sequenceColumn = null;

    /**
     * Constructor
     *
     * @param iterable<Identifier>|string $trackColumns
     */
    public function __construct(bool $breadthFirst, string|iterable $trackColumns, Identifier|string $sequenceColumn)
    {
        $this->generatePropertyNames();
        $this->setBreadthFirst($breadthFirst);
        $this->setTrackColumns($trackColumns);
        $this->setSequenceColumn($sequenceColumn);
    }

    /** @internal Support method for `$breadthFirst` magic property, use the property instead */
    public function setBreadthFirst(bool $breadthFirst): void
    {
        $this->p_breadthFirst = $breadthFirst;
    }

    /**
     * Sets the list of columns to track for sorting
     *
     * @param string|iterable<Identifier> $columns
     * @internal Support method for `$trackColumns` magic property, use the property instead
     */
    public function setTrackColumns(string|iterable $columns): void
    {
        if (\is_string($columns)) {
            $columns = $this->getParserOrFail('a column list for a SEARCH clause')->parseColIdList($columns);
        } elseif (!$columns instanceof IdentifierList) {
            $columns = new IdentifierList($columns);
        }
        if (null !== $this->p_trackColumns) {
            $this->setRequiredProperty($this->p_trackColumns, $columns);
        } else {
            // Called from constructor
            $this->p_trackColumns = $columns;
            $this->p_trackColumns->setParentNode($this);
        }
    }

    /**
     * Sets the name of the column that can be used for sorting
     *
     * @internal Support method for `$sequenceColumn` magic property, use the property instead
     */
    public function setSequenceColumn(Identifier|string $column): void
    {
        if (!$column instanceof Identifier) {
            $column = new Identifier($column);
        }
        if (null !== $this->p_sequenceColumn) {
            $this->setRequiredProperty($this->p_sequenceColumn, $column);
        } else {
            // Called from constructor
            $this->p_sequenceColumn = $column;
            $this->p_sequenceColumn->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSearchClause($this);
    }
}
