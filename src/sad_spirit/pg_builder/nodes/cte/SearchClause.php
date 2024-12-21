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
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * SEARCH BREADTH / DEPTH FIRST clause for Common Table Expressions
 *
 * @psalm-property IdentifierList $trackColumns
 *
 * @property bool                        $breadthFirst
 * @property IdentifierList|Identifier[] $trackColumns
 * @property Identifier                  $sequenceColumn
 */
class SearchClause extends GenericNode
{
    use NonRecursiveNode;

    /** @var bool */
    protected $p_breadthFirst;
    /** @var IdentifierList|null */
    protected $p_trackColumns;
    /** @var Identifier|null */
    protected $p_sequenceColumn;

    /**
     * Constructor
     *
     * @param bool                        $breadthFirst
     * @param iterable<Identifier>|string $trackColumns
     * @param Identifier|string           $sequenceColumn
     */
    public function __construct(bool $breadthFirst, $trackColumns, $sequenceColumn)
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
     * @param iterable<Identifier>|string $columns
     */
    public function setTrackColumns($columns): void
    {
        if (!$columns instanceof IdentifierList) {
            if (is_string($columns)) {
                $columns = $this->getParserOrFail('a column list for a SEARCH clause')->parseColIdList($columns);
            } elseif (is_iterable($columns)) {
                $columns = new IdentifierList($columns);
            } else {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an SQL string, an array of identifiers or an instance of IdentifierList, %s given',
                    __METHOD__,
                    is_object($columns) ? 'object(' . get_class($columns) . ')' : gettype($columns)
                ));
            }
        }
        if (!empty($this->p_trackColumns)) {
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
     * @param Identifier|string $column
     */
    public function setSequenceColumn($column): void
    {
        if (!$column instanceof Identifier) {
            $column = new Identifier($column);
        }
        if (!empty($this->p_sequenceColumn)) {
            $this->setRequiredProperty($this->p_sequenceColumn, $column);
        } else {
            // Called from constructor
            $this->p_sequenceColumn = $column;
            $this->p_sequenceColumn->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSearchClause($this);
    }
}
