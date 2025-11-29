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
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing logical NOT operator applied to an expression
 *
 * @property ScalarExpression $argument
 */
class NotExpression extends GenericNode implements ScalarExpression
{
    /** @internal Maps to `$argument` magic property, use the latter instead */
    protected ScalarExpression $p_argument;

    public function __construct(ScalarExpression $argument)
    {
        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);
    }

    /** @internal Support method for `$argument` magic property, use the property instead */
    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkNotExpression($this);
    }

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        return ScalarExpressionPrecedence::NOT;
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::RIGHT;
    }
}
