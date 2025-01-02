<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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

    protected ArrayComparisonConstruct $p_keyword;
    protected ScalarExpression $p_array;

    public function __construct(ArrayComparisonConstruct $keyword, ScalarExpression $array)
    {
        $this->generatePropertyNames();

        $this->p_keyword = $keyword;

        $this->p_array = $array;
        $this->p_array->setParentNode($this);
    }

    public function setArray(ScalarExpression $array): void
    {
        $this->setRequiredProperty($this->p_array, $array);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkArrayComparisonExpression($this);
    }
}
