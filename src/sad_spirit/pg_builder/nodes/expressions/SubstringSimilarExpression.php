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
 * AST node representing SUBSTRING(string SIMILAR pattern ...) function call with special arguments format
 *
 * This function form is supported since Postgres 14
 *
 * @property ScalarExpression $string
 * @property ScalarExpression $pattern
 * @property ScalarExpression $escape
 */
class SubstringSimilarExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @var ScalarExpression */
    protected $p_string;
    /** @var ScalarExpression */
    protected $p_pattern;
    /** @var ScalarExpression */
    protected $p_escape;

    public function __construct(ScalarExpression $string, ScalarExpression $pattern, ScalarExpression $escape)
    {
        if ($string === $pattern || $pattern === $escape || $string === $escape) {
            throw new InvalidArgumentException("Cannot use the same Node for different arguments of SUBSTRING");
        }

        $this->generatePropertyNames();

        $this->p_string = $string;
        $this->p_string->setParentNode($this);

        $this->p_pattern = $pattern;
        $this->p_pattern->setParentNode($this);

        $this->p_escape = $escape;
        $this->p_escape->setParentNode($this);
    }

    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    public function setPattern(ScalarExpression $pattern): void
    {
        $this->setRequiredProperty($this->p_pattern, $pattern);
    }

    public function setEscape(ScalarExpression $escape): void
    {
        $this->setRequiredProperty($this->p_escape, $escape);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSubstringSimilarExpression($this);
    }
}
