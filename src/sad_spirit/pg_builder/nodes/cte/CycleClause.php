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

namespace sad_spirit\pg_builder\nodes\cte;

use sad_spirit\pg_builder\nodes\{
    GenericNode,
    Identifier,
    NonRecursiveNode,
    ScalarExpression,
    expressions\Constant,
    expressions\ConstantTypecastExpression,
    lists\IdentifierList
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * CYCLE clause for Common Table Expressions
 *
 * @psalm-property IdentifierList $trackColumns
 *
 * @property IdentifierList|Identifier[]              $trackColumns
 * @property Identifier                               $markColumn
 * @property Identifier                               $pathColumn
 * @property Constant|ConstantTypecastExpression|null $markValue
 * @property Constant|ConstantTypecastExpression|null $markDefault
 */
class CycleClause extends GenericNode
{
    use NonRecursiveNode;

    /** @var IdentifierList|null */
    protected $p_trackColumns;
    /** @var Identifier|null */
    protected $p_markColumn;
    /** @var Identifier|null */
    protected $p_pathColumn;
    /** @var Constant|ConstantTypecastExpression|null */
    protected $p_markValue;
    /** @var Constant|ConstantTypecastExpression|null */
    protected $p_markDefault;

    /**
     * Constructor
     *
     * @param iterable<Identifier>|string              $trackColumns
     * @param Identifier|string                        $markColumn
     * @param Identifier|string                        $pathColumn
     * @param Constant|ConstantTypecastExpression|null $markValue
     * @param Constant|ConstantTypecastExpression|null $markDefault
     */
    public function __construct(
        $trackColumns,
        $markColumn,
        $pathColumn,
        ScalarExpression $markValue = null,
        ScalarExpression $markDefault = null
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
     * @param iterable<Identifier>|string $columns
     */
    public function setTrackColumns($columns): void
    {
        if (!$columns instanceof IdentifierList) {
            if (is_string($columns)) {
                $columns = $this->getParserOrFail('a column list for a CYCLE clause')->parseColIdList($columns);
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
     * Sets the name of the column that will be used for marking cycle detection
     *
     * @param Identifier|string $column
     */
    public function setMarkColumn($column): void
    {
        if (!$column instanceof Identifier) {
            $column = new Identifier($column);
        }
        if (!empty($this->p_markColumn)) {
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
     * @param Identifier|string $column
     */
    public function setPathColumn($column): void
    {
        if (!$column instanceof Identifier) {
            $column = new Identifier($column);
        }
        if (!empty($this->p_pathColumn)) {
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
     * @param Constant|ConstantTypecastExpression|null $markValue
     */
    public function setMarkValue(?ScalarExpression $markValue): void
    {
        if (
            null !== $markValue
            && !$markValue instanceof Constant
            && !$markValue instanceof ConstantTypecastExpression
        ) {
            throw new InvalidArgumentException(sprintf(
                '$markValue can only be a constant expression, %s given',
                get_class($markValue)
            ));
        }
        $this->setProperty($this->p_markValue, $markValue);
    }

    /**
     * Sets the constant value that will be assigned to $markColumn when cycle is NOT detected
     *
     * @param ScalarExpression|null $markDefault
     */
    public function setMarkDefault(?ScalarExpression $markDefault): void
    {
        if (
            null !== $markDefault
            && !$markDefault instanceof Constant
            && !$markDefault instanceof ConstantTypecastExpression
        ) {
            throw new InvalidArgumentException(sprintf(
                '$markDefault can only be a constant expression, %s given',
                get_class($markDefault)
            ));
        }
        $this->setProperty($this->p_markDefault, $markDefault);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCycleClause($this);
    }
}
