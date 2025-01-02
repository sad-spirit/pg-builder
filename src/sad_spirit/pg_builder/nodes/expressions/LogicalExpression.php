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

    protected LogicalOperator $p_operator;

    protected $propertyNames = [
        'operator' => 'p_operator'
    ];

    /**
     * LogicalExpression constructor
     *
     * @param null|string|iterable<ScalarExpression> $terms
     * @param LogicalOperator                        $operator
     */
    public function __construct($terms = null, LogicalOperator $operator = LogicalOperator::AND)
    {
        parent::__construct($terms);
        $this->p_operator = $operator;
    }

    public function dispatch(TreeWalker $walker)
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
