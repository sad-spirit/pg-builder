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

    protected bool $p_breadthFirst;
    protected ?IdentifierList $p_trackColumns = null;
    protected ?Identifier $p_sequenceColumn = null;

    /**
     * Constructor
     *
     * @param bool                        $breadthFirst
     * @param iterable<Identifier>|string $trackColumns
     * @param Identifier|string           $sequenceColumn
     */
    public function __construct(bool $breadthFirst, string|iterable $trackColumns, Identifier|string $sequenceColumn)
    {
        $this->generatePropertyNames();
        $this->setBreadthFirst($breadthFirst);
        $this->setTrackColumns($trackColumns);
        $this->setSequenceColumn($sequenceColumn);
    }

    public function setBreadthFirst(bool $breadthFirst): void
    {
        $this->p_breadthFirst = $breadthFirst;
    }

    /**
     * Sets the list of columns to track for sorting
     *
     * @param string|iterable<Identifier> $columns
     */
    public function setTrackColumns(string|iterable $columns): void
    {
        if (is_string($columns)) {
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
