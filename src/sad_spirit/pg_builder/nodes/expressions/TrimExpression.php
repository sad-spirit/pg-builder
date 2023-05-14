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
 * @copyright 2014-2023 Alexey Borzov
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
    lists\ExpressionList,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing TRIM(...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.(btrim|ltrim|rtrim) as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * NB: we define $arguments as ExpressionList as this is what Postgres itself does (see trim_list grammar
 * production). However, passing more than two arguments will result in a "function does not exist" error and
 * thus Postgres only outputs at most two arguments when generating SQL in src/backend/utils/adt/ruleutils.c
 * Obviously, we cannot  check for function existence, so just pass on all arguments in generated SQL.
 *
 * @psalm-property ExpressionList $arguments
 *
 * @property      ExpressionList|ScalarExpression[] $arguments
 * @property-read string                            $side
 */
class TrimExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    public const LEADING  = 'leading';
    public const TRAILING = 'trailing';
    public const BOTH     = 'both';

    public const SIDES = [
        self::LEADING,
        self::TRAILING,
        self::BOTH
    ];

    /** @var ExpressionList */
    protected $p_arguments;
    /** @var string */
    protected $p_side;

    public function __construct(ExpressionList $arguments, string $side = self::BOTH)
    {
        if (!in_array($side, self::SIDES, true)) {
            throw new InvalidArgumentException("Unknown value '{$side}' for \$side parameter of TrimExpression");
        }

        $this->generatePropertyNames();

        $this->p_arguments = $arguments;
        $this->p_arguments->setParentNode($this);

        $this->p_side = $side;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkTrimExpression($this);
    }
}
