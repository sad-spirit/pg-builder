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

    /**
     * LogicalExpression constructor
     *
     * @param null|string|iterable<ScalarExpression> $terms
     * @param LogicalOperator $p_operator
     */
    public function __construct(
        string|iterable|null $terms = null,
        protected LogicalOperator $p_operator = LogicalOperator::AND
    ) {
        parent::__construct($terms);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkLogicalExpression($this);
    }

    public function getPrecedence(): int
    {
        return match ($this->p_operator) {
            LogicalOperator::AND => self::PRECEDENCE_AND,
            LogicalOperator::OR => self::PRECEDENCE_OR
        };
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
