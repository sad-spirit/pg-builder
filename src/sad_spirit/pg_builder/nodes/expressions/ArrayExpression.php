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
    Node,
    nodes\ExpressionAtom,
    nodes\ScalarExpression,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents an array constructed from a list of values: ARRAY[...]
 *
 * @extends NonAssociativeList<
 *      ScalarExpression,
 *      iterable<ScalarExpression|iterable<ScalarExpression>>,
 *      ScalarExpression|iterable<ScalarExpression>
 * >
 */
class ArrayExpression extends NonAssociativeList implements ScalarExpression
{
    use ExpressionAtom;

    protected static function getAllowedElementClasses(): array
    {
        return [ScalarExpression::class];
    }

    protected function prepareListElement($value): Node
    {
        if (\is_iterable($value) && !$value instanceof ScalarExpression) {
            $value = new self($value);
        }
        return parent::prepareListElement($value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkArrayExpression($this);
    }
}
