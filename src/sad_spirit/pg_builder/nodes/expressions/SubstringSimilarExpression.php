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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing SUBSTRING(string SIMILAR pattern ...) function call with special arguments format
 *
 * This function form is supported since Postgres 14
 *
 * @property ScalarExpression $string
 * @property ScalarExpression $pattern
 * @property ScalarExpression $escape
 */
class SubstringSimilarExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @internal Maps to `$string` magic property, use the latter instead */
    protected ScalarExpression $p_string;
    /** @internal Maps to `$pattern` magic property, use the latter instead */
    protected ScalarExpression $p_pattern;
    /** @internal Maps to `$escape` magic property, use the latter instead */
    protected ScalarExpression $p_escape;

    public function __construct(ScalarExpression $string, ScalarExpression $pattern, ScalarExpression $escape)
    {
        if ($string === $pattern || $pattern === $escape || $string === $escape) {
            throw new InvalidArgumentException("Cannot use the same Node for different arguments of SUBSTRING");
        }

        $this->generatePropertyNames();

        $this->p_string = $string;
        $this->p_string->setParentNode($this);

        $this->p_pattern = $pattern;
        $this->p_pattern->setParentNode($this);

        $this->p_escape = $escape;
        $this->p_escape->setParentNode($this);
    }

    /** @internal Support method for `$string` magic property, use the property instead */
    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    /** @internal Support method for `$pattern` magic property, use the property instead */
    public function setPattern(ScalarExpression $pattern): void
    {
        $this->setRequiredProperty($this->p_pattern, $pattern);
    }

    /** @internal Support method for `$escape` magic property, use the property instead */
    public function setEscape(ScalarExpression $escape): void
    {
        $this->setRequiredProperty($this->p_escape, $escape);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSubstringSimilarExpression($this);
    }
}
