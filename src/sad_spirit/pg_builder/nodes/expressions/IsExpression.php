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

use sad_spirit\pg_builder\enums\IsPredicate;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo IS [NOT] keyword" expression
 *
 * Allowed keywords are TRUE / FALSE / NULL / UNKNOWN / DOCUMENT / [NFC|NFD|NFKC|NFKD] NORMALIZED
 *
 * @property ScalarExpression $argument
 * @property IsPredicate      $what
 */
class IsExpression extends NegatableExpression
{
    protected ScalarExpression $p_argument;
    protected IsPredicate $p_what;

    public function __construct(ScalarExpression $argument, IsPredicate $what, bool $not = false)
    {
        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_not = $not;

        $this->setWhat($what);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setWhat(IsPredicate $what): void
    {
        $this->p_what = $what;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkIsExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IS;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
