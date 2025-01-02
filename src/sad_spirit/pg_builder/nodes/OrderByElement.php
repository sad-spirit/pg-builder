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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    TreeWalker,
    enums\NullsOrder,
    enums\OrderByDirection,
    exceptions\InvalidArgumentException
};

/**
 * AST node representing an expression from ORDER BY clause
 *
 * @property      ScalarExpression              $expression
 * @property-read OrderByDirection|null         $direction
 * @property-read NullsOrder|null               $nullsOrder
 * @property-read string|QualifiedOperator|null $operator
 */
class OrderByElement extends GenericNode
{
    protected ScalarExpression $p_expression;
    protected ?OrderByDirection $p_direction = null;
    protected ?NullsOrder $p_nullsOrder = null;
    protected string|QualifiedOperator|null $p_operator;

    public function __construct(
        ScalarExpression $expression,
        ?OrderByDirection $direction = null,
        ?NullsOrder $nullsOrder = null,
        string|QualifiedOperator|null $operator = null
    ) {
        if (OrderByDirection::USING === $direction && null === $operator) {
            throw new InvalidArgumentException("Operator required for USING sort direction");
        }

        $this->generatePropertyNames();
        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);

        $this->p_direction  = $direction;
        $this->p_nullsOrder = $nullsOrder;

        $this->p_operator   = $operator;
        if ($this->p_operator instanceof QualifiedOperator) {
            $this->p_operator->setParentNode($this);
        }
    }

    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOrderByElement($this);
    }
}
