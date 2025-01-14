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
    enums\PatternPredicate,
    exceptions\InvalidArgumentException,
    nodes\ScalarExpression
};

/**
 * AST node representing [NOT] LIKE | ILIKE | SIMILAR TO operators
 *
 * These cannot be represented by a standard Operator node as they can have a
 * trailing ESCAPE clause
 *
 * @property      ScalarExpression      $argument
 * @property      ScalarExpression      $pattern
 * @property      ScalarExpression|null $escape
 * @property-read PatternPredicate      $operator
 */
class PatternMatchingExpression extends NegatableExpression
{
    protected ScalarExpression $p_argument;
    protected ScalarExpression $p_pattern;
    protected PatternPredicate $p_operator;
    protected ?ScalarExpression $p_escape = null;

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $pattern,
        PatternPredicate $operator = PatternPredicate::LIKE,
        bool $not = false,
        ?ScalarExpression $escape = null
    ) {
        if ($argument === $pattern || $argument === $escape || $pattern === $escape) {
            throw new InvalidArgumentException("Cannot use the same Node for argument / pattern / escape");
        }

        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_pattern = $pattern;
        $this->p_pattern->setParentNode($this);

        $this->p_operator = $operator;

        if (null !== $escape) {
            $this->p_escape = $escape;
            $this->p_escape->setParentNode($this);
        }
        $this->p_not      = $not;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setPattern(ScalarExpression $pattern): void
    {
        $this->setRequiredProperty($this->p_pattern, $pattern);
    }

    public function setEscape(?ScalarExpression $escape): void
    {
        $this->setProperty($this->p_escape, $escape);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkPatternMatchingExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_PATTERN;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
