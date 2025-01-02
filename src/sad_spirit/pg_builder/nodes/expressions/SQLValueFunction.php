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
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\enums\SQLValueFunctionName;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Node for parameterless functions with special grammar productions
 *
 * Direct interpretation of SQLValueFunction in src/include/nodes/primnodes.h
 *
 * Previously these functions were converted either to FunctionCall nodes with corresponding functions from pg_catalog,
 * or to TypecastExpression nodes. Better (and probably more compatible) is to keep them as-is.
 *
 * @property-read SQLValueFunctionName $name
 * @property-read NumericConstant|null $modifier
 */
class SQLValueFunction extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    protected SQLValueFunctionName $p_name;
    protected ?NumericConstant $p_modifier;

    public function __construct(SQLValueFunctionName $name, ?NumericConstant $modifier = null)
    {
        if (null !== $modifier && !$name->allowsModifiers()) {
            throw new InvalidArgumentException("SQLValueFunction '$name->value' does not accept modifiers");
        }

        $this->generatePropertyNames();
        $this->p_name     = $name;
        $this->p_modifier = $modifier;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSQLValueFunction($this);
    }
}
