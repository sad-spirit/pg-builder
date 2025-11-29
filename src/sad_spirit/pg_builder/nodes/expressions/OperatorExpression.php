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
    enums\ScalarExpressionAssociativity,
    enums\ScalarExpressionPrecedence,
    exceptions\InvalidArgumentException,
    exceptions\SyntaxException,
    nodes\GenericNode,
    nodes\QualifiedOperator,
    nodes\ScalarExpression,
    Lexer,
    TreeWalker
};

/**
 * AST node representing a generic operator-like expression
 *
 * This is used for constructs of the form
 *  * <operator> <right argument>
 *  * <left argument> <operator> <right argument>
 * Operator here is roughly equivalent to qual_all_Op production from grammar
 *
 * @property      ScalarExpression|null    $left
 * @property      ScalarExpression         $right
 * @property-read string|QualifiedOperator $operator
 */
class OperatorExpression extends GenericNode implements ScalarExpression
{
    private const PRECEDENCES = [
        '='  => ScalarExpressionPrecedence::COMPARISON,
        '<'  => ScalarExpressionPrecedence::COMPARISON,
        '>'  => ScalarExpressionPrecedence::COMPARISON,
        '<=' => ScalarExpressionPrecedence::COMPARISON,
        '>=' => ScalarExpressionPrecedence::COMPARISON,
        '!=' => ScalarExpressionPrecedence::COMPARISON,
        '<>' => ScalarExpressionPrecedence::COMPARISON,

        '+'  => ScalarExpressionPrecedence::ADDITION,
        '-'  => ScalarExpressionPrecedence::ADDITION,

        '*'  => ScalarExpressionPrecedence::MULTIPLICATION,
        '/'  => ScalarExpressionPrecedence::MULTIPLICATION,
        '%'  => ScalarExpressionPrecedence::MULTIPLICATION,

        '^' => ScalarExpressionPrecedence::EXPONENTIATION
    ];

    private const PRECEDENCES_UNARY = [
        '+' => ScalarExpressionPrecedence::UNARY_MINUS,
        '-' => ScalarExpressionPrecedence::UNARY_MINUS
    ];

    /** @internal Maps to `$left` magic property, use the latter instead */
    protected ScalarExpression|null $p_left = null;
    /** @internal Maps to `$right` magic property, use the latter instead */
    protected ScalarExpression $p_right;
    /** @internal Maps to `$operator` magic property, use the latter instead */
    protected string|QualifiedOperator $p_operator;

    public function __construct(string|QualifiedOperator $operator, ?ScalarExpression $left, ScalarExpression $right)
    {
        if (\is_string($operator) && \strlen($operator) !== \strspn($operator, Lexer::CHARS_OPERATOR)) {
            throw new SyntaxException(\sprintf(
                "%s: '%s' does not look like a valid operator string",
                self::class,
                $operator
            ));
        }
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for left and right operands");
        }

        $this->generatePropertyNames();

        if (null !== $left) {
            $this->p_left = $left;
            $this->p_left->setParentNode($this);
        }
        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->p_operator = $operator;
        if ($this->p_operator instanceof QualifiedOperator) {
            $this->p_operator->setParentNode($this);
        }
    }

    /** @internal Support method for `$left` magic property, use the property instead */
    public function setLeft(?ScalarExpression $left): void
    {
        $this->setProperty($this->p_left, $left);
    }

    /** @internal Support method for `$right` magic property, use the property instead */
    public function setRight(ScalarExpression $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkOperatorExpression($this);
    }

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        if (!\is_string($this->p_operator) || !isset(self::PRECEDENCES[$this->p_operator])) {
            return ScalarExpressionPrecedence::GENERIC_OP;
        } elseif (null === $this->p_left && isset(self::PRECEDENCES_UNARY[$this->p_operator])) {
            return self::PRECEDENCES_UNARY[$this->p_operator];
        } else {
            return self::PRECEDENCES[$this->p_operator];
        }
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        if (!\is_string($this->p_operator)) {
            return ScalarExpressionAssociativity::LEFT;
        } elseif (null === $this->p_left && isset(self::PRECEDENCES_UNARY[$this->p_operator])) {
            return ScalarExpressionAssociativity::RIGHT;
        } elseif (
            !isset(self::PRECEDENCES[$this->p_operator])
            || ScalarExpressionPrecedence::COMPARISON !== self::PRECEDENCES[$this->p_operator]
        ) {
            return ScalarExpressionAssociativity::LEFT;
        } else {
            return ScalarExpressionAssociativity::NONE;
        }
    }
}
