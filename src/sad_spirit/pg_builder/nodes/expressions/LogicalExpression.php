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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    TreeWalker,
    enums\LogicalOperator,
    enums\ScalarExpressionAssociativity,
    enums\ScalarExpressionPrecedence,
    nodes\HasBothPropsAndOffsets,
    nodes\ScalarExpression
};
use sad_spirit\pg_builder\nodes\lists\ExpressionList;

/**
 * AST node representing a group of expressions combined by `AND` or `OR` operators
 *
 * @property-read LogicalOperator $operator
 */
class LogicalExpression extends ExpressionList implements ScalarExpression
{
    use HasBothPropsAndOffsets;

    /** @var array<string, string> */
    protected array $propertyNames = [
        'operator' => 'p_operator'
    ];
    /** @internal Maps to `$operator` magic property, use the latter instead */
    protected LogicalOperator $p_operator;

    /**
     * LogicalExpression constructor
     *
     * @param null|string|iterable<ScalarExpression> $terms
     */
    public function __construct(string|iterable|null $terms = null, LogicalOperator $operator = LogicalOperator::AND)
    {
        parent::__construct($terms);

        $this->p_operator = $operator;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkLogicalExpression($this);
    }

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        return match ($this->p_operator) {
            LogicalOperator::AND => ScalarExpressionPrecedence::AND,
            LogicalOperator::OR => ScalarExpressionPrecedence::OR
        };
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::LEFT;
    }
}
