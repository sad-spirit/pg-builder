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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo AT TIME ZONE bar" expression
 *
 * @property ScalarExpression $argument
 * @property ScalarExpression $timeZone
 */
class AtTimeZoneExpression extends GenericNode implements ScalarExpression
{
    protected ScalarExpression $p_argument;
    protected ScalarExpression $p_timeZone;

    public function __construct(ScalarExpression $argument, ScalarExpression $timeZone)
    {
        if ($argument === $timeZone) {
            throw new InvalidArgumentException("Cannot use the same Node for argument and time zone");
        }

        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_timeZone = $timeZone;
        $this->p_timeZone->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setTimeZone(ScalarExpression $timeZone): void
    {
        $this->setRequiredProperty($this->p_timeZone, $timeZone);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkAtTimeZoneExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_TIME_ZONE;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
