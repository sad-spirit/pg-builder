<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    SelectCommon,
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\lists\ExpressionList;

/**
 * AST node representing a [NOT] IN expression
 *
 * Cannot be an OperatorExpression due to specific right operands
 *
 * @property      ScalarExpression            $left
 * @property      SelectCommon|ExpressionList $right
 * @property-read string                      $operator
 */
class InExpression extends GenericNode implements ScalarExpression
{
    public const IN     = 'in';
    public const NOT_IN = 'not in';

    private const ALLOWED_OPERATORS = [
        self::IN     => true,
        self::NOT_IN => true
    ];

    public function __construct(ScalarExpression $left, $right, string $operator = self::IN)
    {
        if (!isset(self::ALLOWED_OPERATORS[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for IN-style expression");
        }
        $this->setRight($right);
        $this->setLeft($left);
        $this->props['operator'] = $operator;
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setNamedProperty('left', $left);
    }

    public function setRight($right): void
    {
        if (!($right instanceof SelectCommon) && !($right instanceof ExpressionList)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an instance of either SelectCommon or ExpressionList as right operand, %s given',
                __CLASS__,
                is_object($right) ? 'object(' . get_class($right) . ')' : gettype($right)
            ));
        }
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkInExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IN;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
