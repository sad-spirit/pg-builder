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
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\enums\ArrayComparisonConstruct;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents ANY / ALL / SOME construct applied to an array-type expression
 *
 * @property ArrayComparisonConstruct $keyword
 * @property ScalarExpression         $array
 */
class ArrayComparisonExpression extends GenericNode implements ScalarExpression
{
    use ExpressionAtom;

    /** @internal Maps to `$keyword` magic property, use the latter instead */
    protected ArrayComparisonConstruct $p_keyword;
    /** @internal Maps to `$array` magic property, use the latter instead */
    protected ScalarExpression $p_array;

    public function __construct(ArrayComparisonConstruct $keyword, ScalarExpression $array)
    {
        $this->generatePropertyNames();

        $this->p_keyword = $keyword;
        $this->p_array   = $array;

        $this->p_array->setParentNode($this);
    }

    /** @internal Support method for `$array` magic property, use the property instead */
    public function setArray(ScalarExpression $array): void
    {
        $this->setRequiredProperty($this->p_array, $array);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkArrayComparisonExpression($this);
    }
}
