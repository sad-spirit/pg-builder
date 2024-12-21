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
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing NULLIF(first, second) construct
 *
 * @property ScalarExpression $first
 * @property ScalarExpression $second
 */
class NullIfExpression extends GenericNode implements FunctionLike, ScalarExpression
{
    use ExpressionAtom;

    /** @var ScalarExpression */
    protected $p_first;
    /** @var ScalarExpression */
    protected $p_second;

    public function __construct(ScalarExpression $first, ScalarExpression $second)
    {
        if ($first === $second) {
            throw new InvalidArgumentException("Cannot use the same Node for both arguments");
        }

        $this->generatePropertyNames();

        $this->p_first = $first;
        $this->p_first->setParentNode($this);

        $this->p_second = $second;
        $this->p_second->setParentNode($this);
    }

    public function setFirst(ScalarExpression $first): void
    {
        $this->setRequiredProperty($this->p_first, $first);
    }

    public function setSecond(ScalarExpression $second): void
    {
        $this->setRequiredProperty($this->p_second, $second);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkNullIfExpression($this);
    }
}
