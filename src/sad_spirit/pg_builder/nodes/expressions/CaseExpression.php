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
    TreeWalker,
    exceptions\InvalidArgumentException,
    nodes\ExpressionAtom,
    nodes\HasBothPropsAndOffsets,
    nodes\ScalarExpression
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents a CASE expression (with or without argument)
 *
 * @property ScalarExpression|null $argument
 * @property ScalarExpression|null $else
 * @extends NonAssociativeList<WhenExpression, iterable<WhenExpression>, WhenExpression>
 */
class CaseExpression extends NonAssociativeList implements ScalarExpression
{
    use ExpressionAtom;
    use HasBothPropsAndOffsets;

    protected ?ScalarExpression $p_argument = null;
    protected ?ScalarExpression $p_else = null;

    protected static function getAllowedElementClasses(): array
    {
        return [WhenExpression::class];
    }

    /**
     * CaseExpression constructor
     *
     * @param iterable<WhenExpression> $whenClauses
     * @param ScalarExpression|null    $elseClause
     * @param ScalarExpression|null    $argument
     */
    public function __construct(
        iterable $whenClauses,
        ?ScalarExpression $elseClause = null,
        ?ScalarExpression $argument = null
    ) {
        if (null !== $elseClause && $elseClause === $argument) {
            throw new InvalidArgumentException("Cannot use the same Node for CASE argument and ELSE clause");
        }

        $this->generatePropertyNames();
        parent::__construct($whenClauses);
        if (1 > \count($this->offsets)) {
            throw new InvalidArgumentException(self::class . ': at least one WHEN clause is required');
        }

        if (null !== $argument) {
            $this->p_argument = $argument;
            $this->p_argument->setParentNode($this);
        }
        if (null !== $elseClause) {
            $this->p_else = $elseClause;
            $this->p_else->setParentNode($this);
        }
    }

    public function setArgument(?ScalarExpression $argument = null): void
    {
        $this->setProperty($this->p_argument, $argument);
    }

    public function setElse(?ScalarExpression $elseClause = null): void
    {
        $this->setProperty($this->p_else, $elseClause);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkCaseExpression($this);
    }
}
