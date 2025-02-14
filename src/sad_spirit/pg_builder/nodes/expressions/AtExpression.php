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

use sad_spirit\pg_builder\enums\ScalarExpressionAssociativity;
use sad_spirit\pg_builder\enums\ScalarExpressionPrecedence;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Base class for nodes representing `... AT TIME ZONE ...` and `... AT LOCAL` expressions
 *
 * @property ScalarExpression $argument
 */
abstract class AtExpression extends GenericNode implements ScalarExpression
{
    protected ScalarExpression $p_argument;

    public function __construct(ScalarExpression $argument)
    {
        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        return ScalarExpressionPrecedence::TIME_ZONE;
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::LEFT;
    }
}
