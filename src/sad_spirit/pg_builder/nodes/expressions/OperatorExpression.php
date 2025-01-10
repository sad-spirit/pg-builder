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
        '='  => self::PRECEDENCE_COMPARISON,
        '<'  => self::PRECEDENCE_COMPARISON,
        '>'  => self::PRECEDENCE_COMPARISON,
        '<=' => self::PRECEDENCE_COMPARISON,
        '>=' => self::PRECEDENCE_COMPARISON,
        '!=' => self::PRECEDENCE_COMPARISON,
        '<>' => self::PRECEDENCE_COMPARISON,

        '+'  => self::PRECEDENCE_ADDITION,
        '-'  => self::PRECEDENCE_ADDITION,

        '*'  => self::PRECEDENCE_MULTIPLICATION,
        '/'  => self::PRECEDENCE_MULTIPLICATION,
        '%'  => self::PRECEDENCE_MULTIPLICATION,

        '^' => self::PRECEDENCE_EXPONENTIATION
    ];

    private const PRECEDENCES_UNARY = [
        '+' => self::PRECEDENCE_UNARY_MINUS,
        '-' => self::PRECEDENCE_UNARY_MINUS
    ];

    protected ScalarExpression|null $p_left = null;
    protected ScalarExpression $p_right;
    protected string|QualifiedOperator $p_operator;

    public function __construct(string|QualifiedOperator $operator, ?ScalarExpression $left, ScalarExpression $right)
    {
        if (is_string($operator) && strlen($operator) !== strspn($operator, Lexer::CHARS_OPERATOR)) {
            throw new SyntaxException(sprintf(
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

    public function setLeft(?ScalarExpression $left): void
    {
        $this->setProperty($this->p_left, $left);
    }

    public function setRight(ScalarExpression $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkOperatorExpression($this);
    }

    public function getPrecedence(): int
    {
        if (!is_string($this->p_operator) || !isset(self::PRECEDENCES[$this->p_operator])) {
            return self::PRECEDENCE_GENERIC_OP;
        } elseif (null === $this->p_left && isset(self::PRECEDENCES_UNARY[$this->p_operator])) {
            return self::PRECEDENCES_UNARY[$this->p_operator];
        } else {
            return self::PRECEDENCES[$this->p_operator];
        }
    }

    public function getAssociativity(): string
    {
        if (!is_string($this->p_operator)) {
            return self::ASSOCIATIVE_LEFT;
        } elseif (null === $this->p_left && isset(self::PRECEDENCES_UNARY[$this->p_operator])) {
            return self::ASSOCIATIVE_RIGHT;
        } elseif (
            !isset(self::PRECEDENCES[$this->p_operator])
            || self::PRECEDENCE_COMPARISON !== self::PRECEDENCES[$this->p_operator]
        ) {
            return self::ASSOCIATIVE_LEFT;
        } else {
            return self::ASSOCIATIVE_NONE;
        }
    }
}
