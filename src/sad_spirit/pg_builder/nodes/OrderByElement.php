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
    /** @internal Maps to `$expression` magic property, use the latter instead */
    protected ScalarExpression $p_expression;
    /** @internal Maps to `$direction` magic property, use the latter instead */
    protected ?OrderByDirection $p_direction = null;
    /** @internal Maps to `$nullsOrder` magic property, use the latter instead */
    protected ?NullsOrder $p_nullsOrder = null;
    /** @internal Maps to `$operator` magic property, use the latter instead */
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

    /** @internal Support method for `$expression` magic property, use the property instead */
    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkOrderByElement($this);
    }
}
