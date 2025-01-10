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
    ScalarExpression,
    lists\ExpressionList
};
use sad_spirit\pg_builder\enums\SystemFunctionName;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Node for function calls with special grammar productions
 *
 * This represents parts of func_expr_common_subexpr grammar rule that defines functions that have
 *   - an SQL keyword for a name ('coalesce', 'greatest', 'least', 'xmlconcat')
 *   - an expr_list (our ExpressionList) for arguments
 *
 * Formerly these calls were represented by FunctionCall / FunctionExpression with $name given as string,
 * but that caused at least two problems:
 *   - checks whether $name is string or QualifiedName had to be added everywhere
 *   - FunctionCall has several other properties that are invalid for system functions
 *
 * @property-read SystemFunctionName $name
 * @property      ExpressionList     $arguments
 */
class SystemFunctionCall extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    public function __construct(protected SystemFunctionName $p_name, protected ExpressionList $p_arguments)
    {
        $this->generatePropertyNames();
        $this->p_arguments->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSystemFunctionCall($this);
    }
}
