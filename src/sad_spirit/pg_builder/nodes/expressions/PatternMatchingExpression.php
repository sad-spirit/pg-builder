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
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
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
 * @property-read string                $operator either of 'like' / 'ilike' / 'similar to'
 * @property      bool                  $negated  set to true for NOT LIKE and other negated expressions
 */
class PatternMatchingExpression extends GenericNode implements ScalarExpression
{
    public const LIKE       = 'like';
    public const ILIKE      = 'ilike';
    public const SIMILAR_TO = 'similar to';

    private const ALLOWED_OPERATORS = [
        self::LIKE           => true,
        self::ILIKE          => true,
        self::SIMILAR_TO     => true
    ];

    /** @var ScalarExpression */
    protected $p_argument;
    /** @var ScalarExpression */
    protected $p_pattern;
    /** @var ScalarExpression|null */
    protected $p_escape;
    /** @var string */
    protected $p_operator;
    /** @var bool */
    protected $p_negated;

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $pattern,
        string $operator = self::LIKE,
        bool $negated = false,
        ScalarExpression $escape = null
    ) {
        if (!isset(self::ALLOWED_OPERATORS[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for pattern matching expression");
        }
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

        $this->p_operator = $operator;
        $this->p_negated  = $negated;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setPattern(ScalarExpression $pattern): void
    {
        $this->setRequiredProperty($this->p_pattern, $pattern);
    }

    public function setEscape(ScalarExpression $escape = null): void
    {
        $this->setProperty($this->p_escape, $escape);
    }

    public function setNegated(bool $negated): void
    {
        $this->p_negated = $negated;
    }

    public function dispatch(TreeWalker $walker)
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
