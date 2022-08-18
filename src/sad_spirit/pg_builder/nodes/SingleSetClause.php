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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a single "column_name = expression" clause of UPDATE statement
 *
 * @property-read SetTargetElement              $column
 * @property      ScalarExpression|SetToDefault $value
 */
class SingleSetClause extends GenericNode
{
    /** @var SetTargetElement */
    protected $p_column;
    /** @var ScalarExpression|SetToDefault */
    protected $p_value;

    /**
     * SingleSetClause constructor
     *
     * @param SetTargetElement              $column
     * @param ScalarExpression|SetToDefault $value
     */
    public function __construct(SetTargetElement $column, Node $value)
    {
        if (!($value instanceof ScalarExpression) && !($value instanceof SetToDefault)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a ScalarExpression or SetToDefault instance as value, %s given',
                __CLASS__,
                get_class($value)
            ));
        }

        $this->generatePropertyNames();
        $this->p_column = $column;
        $this->p_column->setParentNode($this);

        $this->p_value = $value;
        $this->p_value->setParentNode($this);
    }

    /**
     * Sets the Node representing a new value for the column
     *
     * @param ScalarExpression|SetToDefault $value
     */
    public function setValue(Node $value): void
    {
        if (!($value instanceof ScalarExpression) && !($value instanceof SetToDefault)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects either a ScalarExpression or SetToDefault instance as value, %s given',
                __CLASS__,
                get_class($value)
            ));
        }
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSingleSetClause($this);
    }
}
