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
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing POSITION(...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.position as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property ScalarExpression $substring
 * @property ScalarExpression $string
 */
class PositionExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @var ScalarExpression */
    protected $p_substring;
    /** @var ScalarExpression */
    protected $p_string;

    public function __construct(ScalarExpression $substring, ScalarExpression $string)
    {
        if ($substring === $string) {
            throw new InvalidArgumentException("Cannot use the same Node for both arguments of POSITION");
        }

        $this->generatePropertyNames();

        $this->p_substring = $substring;
        $this->p_substring->setParentNode($this);

        $this->p_string = $string;
        $this->p_string->setParentNode($this);
    }

    public function setSubstring(ScalarExpression $substring): void
    {
        $this->setRequiredProperty($this->p_substring, $substring);
    }

    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkPositionExpression($this);
    }
}
