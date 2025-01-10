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
    protected ?ScalarExpression $p_escape = null;

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $pattern,
        protected PatternPredicate $p_operator = PatternPredicate::LIKE,
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
