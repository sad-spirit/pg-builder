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
    expressions\Constant,
    expressions\ConstantTypecastExpression,
    lists\IdentifierList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * CYCLE clause for Common Table Expressions
 *
 * @property IdentifierList                           $trackColumns
 * @property Identifier                               $markColumn
 * @property Identifier                               $pathColumn
 * @property Constant|ConstantTypecastExpression|null $markValue
 * @property Constant|ConstantTypecastExpression|null $markDefault
 */
class CycleClause extends GenericNode
{
    use NonRecursiveNode;

    /** @internal Maps to `$trackColumns` magic property, use the latter instead */
    protected ?IdentifierList $p_trackColumns = null;
    /** @internal Maps to `$markColumn` magic property, use the latter instead */
    protected ?Identifier $p_markColumn = null;
    /** @internal Maps to `$pathColumn` magic property, use the latter instead */
    protected ?Identifier $p_pathColumn = null;
    /** @internal Maps to `$markValue` magic property, use the latter instead */
    protected Constant|ConstantTypecastExpression|null $p_markValue;
    /** @internal Maps to `$markDefault` magic property, use the latter instead */
    protected Constant|ConstantTypecastExpression|null $p_markDefault;

    /**
     * Constructor
     *
     * @param iterable<Identifier>|string $trackColumns
     */
    public function __construct(
        string|iterable $trackColumns,
        Identifier|string $markColumn,
        Identifier|string $pathColumn,
        Constant|ConstantTypecastExpression|null $markValue = null,
        Constant|ConstantTypecastExpression|null $markDefault = null
    ) {
        $this->generatePropertyNames();
        $this->setTrackColumns($trackColumns);
        $this->setMarkColumn($markColumn);
        $this->setPathColumn($pathColumn);
        if (null !== $markValue) {
            $this->setMarkValue($markValue);
        }
        if (null !== $markDefault) {
            $this->setMarkDefault($markDefault);
        }
    }

    /**
     * Sets the list of columns to track for cycles
     *
     * @param string|iterable<Identifier> $columns
     * @internal Support method for `$trackColumns` magic property, use the property instead
     */
    public function setTrackColumns(string|iterable $columns): void
    {
        if (\is_string($columns)) {
            $columns = $this->getParserOrFail('a column list for a CYCLE clause')->parseColIdList($columns);
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
     * Sets the name of the column that will be used for marking cycle detection
     *
     * @internal Support method for `$markColumn` magic property, use the property instead
     */
    public function setMarkColumn(Identifier|string $column): void
    {
        if (!$column instanceof Identifier) {
            $column = new Identifier($column);
        }
        if (null !== $this->p_markColumn) {
            $this->setRequiredProperty($this->p_markColumn, $column);
        } else {
            // Called from constructor
            $this->p_markColumn = $column;
            $this->p_markColumn->setParentNode($this);
        }
    }

    /**
     * Sets the name of the column that will store the path to visited rows
     *
     * @internal Support method for `$pathColumn` magic property, use the property instead
     */
    public function setPathColumn(Identifier|string $column): void
    {
        if (!$column instanceof Identifier) {
            $column = new Identifier($column);
        }
        if (null !== $this->p_pathColumn) {
            $this->setRequiredProperty($this->p_pathColumn, $column);
        } else {
            // Called from constructor
            $this->p_pathColumn = $column;
            $this->p_pathColumn->setParentNode($this);
        }
    }

    /**
     * Sets the constant value that will be assigned to $markColumn when cycle is detected
     *
     * @internal Support method for `$markValue` magic property, use the property instead
     */
    public function setMarkValue(Constant|ConstantTypecastExpression|null $markValue): void
    {
        $this->setProperty($this->p_markValue, $markValue);
    }

    /**
     * Sets the constant value that will be assigned to $markColumn when cycle is NOT detected
     *
     * @internal Support method for `$markDefault` magic property, use the property instead
     */
    public function setMarkDefault(Constant|ConstantTypecastExpression|null $markDefault): void
    {
        $this->setProperty($this->p_markDefault, $markDefault);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkCycleClause($this);
    }
}
