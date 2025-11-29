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
 * AST node representing POSITION(...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.position as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property ScalarExpression $substring
 * @property ScalarExpression $string
 */
class PositionExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @internal Maps to `$substring` magic property, use the latter instead */
    protected ScalarExpression $p_substring;
    /** @internal Maps to `$string` magic property, use the latter instead */
    protected ScalarExpression $p_string;

    public function __construct(ScalarExpression $substring, ScalarExpression $string)
    {
        if ($substring === $string) {
            throw new InvalidArgumentException("Cannot use the same Node for both arguments of POSITION");
        }

        $this->generatePropertyNames();

        $this->p_substring = $substring;
        $this->p_substring->setParentNode($this);

        $this->p_string = $string;
        $this->p_string->setParentNode($this);
    }

    /** @internal Support method for `$substring` magic property, use the property instead */
    public function setSubstring(ScalarExpression $substring): void
    {
        $this->setRequiredProperty($this->p_substring, $substring);
    }

    /** @internal Support method for `$string` magic property, use the property instead */
    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkPositionExpression($this);
    }
}
