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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    Node,
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
 * @psalm-property SelectCommon|ExpressionList $right
 *
 * @property ScalarExpression                               $left
 * @property SelectCommon|ExpressionList|ScalarExpression[] $right
 */
class InExpression extends NegatableExpression
{
    /** @var ScalarExpression */
    protected $p_left;
    /** @var SelectCommon|ExpressionList */
    protected $p_right;

    /**
     * InExpression constructor
     *
     * @param ScalarExpression            $left
     * @param SelectCommon|ExpressionList $right
     * @param bool                        $not
     */
    public function __construct(ScalarExpression $left, Node $right, bool $not = false)
    {
        if (!($right instanceof SelectCommon) && !($right instanceof ExpressionList)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an instance of either SelectCommon or ExpressionList as right operand, %s given',
                __CLASS__,
                get_class($right)
            ));
        }

        $this->generatePropertyNames();

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_not = $not;
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    /**
     * Sets the subselect or a list of expressions appearing in parentheses: foo IN (...)
     *
     * @param SelectCommon|ExpressionList $right
     */
    public function setRight(Node $right): void
    {
        if (!($right instanceof SelectCommon) && !($right instanceof ExpressionList)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an instance of either SelectCommon or ExpressionList as right operand, %s given',
                __CLASS__,
                get_class($right)
            ));
        }
        $this->setRequiredProperty($this->p_right, $right);
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
