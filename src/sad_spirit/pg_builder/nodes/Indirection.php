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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\enums\ScalarExpressionAssociativity;
use sad_spirit\pg_builder\enums\ScalarExpressionPrecedence;
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents an indirection (field selections or array subscripts) applied to an expression
 *
 * @property ScalarExpression $expression
 * @extends NonAssociativeList<
 *     Identifier|ArrayIndexes|Star,
 *     iterable<Identifier|ArrayIndexes|Star>,
 *     Identifier|ArrayIndexes|Star
 * >
 */
class Indirection extends NonAssociativeList implements ScalarExpression
{
    use HasBothPropsAndOffsets;

    protected ScalarExpression $p_expression;

    protected static function getAllowedElementClasses(): array
    {
        return [
            Identifier::class,
            ArrayIndexes::class,
            Star::class
        ];
    }

    /**
     * Indirection constructor
     *
     * @param iterable<Identifier|ArrayIndexes|Star> $indirection
     * @param ScalarExpression $expression
     */
    public function __construct($indirection, ScalarExpression $expression)
    {
        $this->generatePropertyNames();

        parent::__construct($indirection);

        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);
    }

    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkIndirection($this);
    }

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        // actual precedence depends on contents of $nodes
        return ScalarExpressionPrecedence::ATOM;
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::LEFT;
    }
}
