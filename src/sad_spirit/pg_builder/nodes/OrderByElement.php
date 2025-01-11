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
    protected ?OrderByDirection $p_direction = null;
    protected string|QualifiedOperator|null $p_operator;

    public function __construct(
        protected ScalarExpression $p_expression,
        ?OrderByDirection $direction = null,
        protected ?NullsOrder $p_nullsOrder = null,
        string|QualifiedOperator|null $operator = null
    ) {
        if (OrderByDirection::USING === $direction && null === $operator) {
            throw new InvalidArgumentException("Operator required for USING sort direction");
        }

        $this->generatePropertyNames();
        $this->p_expression->setParentNode($this);

        $this->p_direction  = $direction;

        $this->p_operator   = $operator;
        if ($this->p_operator instanceof QualifiedOperator) {
            $this->p_operator->setParentNode($this);
        }
    }

    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkOrderByElement($this);
    }
}
