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
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

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
 * @psalm-property ExpressionList $arguments
 *
 * @property-read string                            $name
 * @property      ExpressionList|ScalarExpression[] $arguments
 */
class SystemFunctionCall extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    public const COALESCE  = 'coalesce';
    public const GREATEST  = 'greatest';
    public const LEAST     = 'least';
    public const XMLCONCAT = 'xmlconcat';

    private const ALLOWED_NAMES = [
        self::COALESCE  => true,
        self::GREATEST  => true,
        self::LEAST     => true,
        self::XMLCONCAT => true
    ];

    /** @var string */
    protected $p_name;
    /** @var ExpressionList */
    protected $p_arguments;

    public function __construct(string $name, ExpressionList $arguments)
    {
        if (!isset(self::ALLOWED_NAMES[$name])) {
            throw new InvalidArgumentException("Unknown function name '{$name}' for SystemFunctionCall");
        }

        $this->generatePropertyNames();
        $this->p_name = $name;

        $this->p_arguments = $arguments;
        $this->p_arguments->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSystemFunctionCall($this);
    }
}
