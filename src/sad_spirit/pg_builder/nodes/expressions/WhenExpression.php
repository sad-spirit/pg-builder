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
    exceptions\InvalidArgumentException,
    exceptions\NotImplementedException,
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * Part of a CASE expression: WHEN Expression THEN Expression
 *
 * @property ScalarExpression $when
 * @property ScalarExpression $then
 */
class WhenExpression extends GenericNode
{
    protected ScalarExpression $p_when;
    protected ScalarExpression $p_then;

    public function __construct(ScalarExpression $when, ScalarExpression $then)
    {
        $this->generatePropertyNames();

        if ($when === $then) {
            throw new InvalidArgumentException("Cannot use the same Node for WHEN and THEN clauses");
        }

        $this->p_when = $when;
        $this->p_when->setParentNode($this);

        $this->p_then = $then;
        $this->p_then->setParentNode($this);
    }

    public function setWhen(ScalarExpression $when): void
    {
        $this->setRequiredProperty($this->p_when, $when);
    }

    public function setThen(ScalarExpression $then): void
    {
        $this->setRequiredProperty($this->p_then, $then);
    }

    public function dispatch(TreeWalker $walker): never
    {
        // handled by dispatch of CaseExpression as this cannot appear outside of CASE
        throw new NotImplementedException('Dispatch for ' . self::class . ' not implemented');
    }
}
