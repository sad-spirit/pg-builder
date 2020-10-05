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
 * @property-read string                $operator
 */
class PatternMatchingExpression extends GenericNode implements ScalarExpression
{
    public const LIKE           = 'like';
    public const NOT_LIKE       = 'not like';
    public const ILIKE          = 'ilike';
    public const NOT_ILIKE      = 'not ilike';
    public const SIMILAR_TO     = 'similar to';
    public const NOT_SIMILAR_TO = 'not similar to';
    
    private const ALLOWED_OPERATORS = [
        self::LIKE           => true,
        self::NOT_LIKE       => true,
        self::ILIKE          => true,
        self::NOT_ILIKE      => true,
        self::SIMILAR_TO     => true,
        self::NOT_SIMILAR_TO => true
    ];

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $pattern,
        string $operator = self::LIKE,
        ScalarExpression $escape = null
    ) {
        if (!isset(self::ALLOWED_OPERATORS[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for pattern matching expression");
        }
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('pattern', $pattern);
        $this->setNamedProperty('escape', $escape);
        $this->props['operator'] = $operator;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function setPattern(ScalarExpression $pattern): void
    {
        $this->setNamedProperty('pattern', $pattern);
    }

    public function setEscape(ScalarExpression $escape = null): void
    {
        $this->setNamedProperty('escape', $escape);
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
