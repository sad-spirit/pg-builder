<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo IS [NOT] DISTINCT FROM bar" expression
 *
 * @property ScalarExpression $left
 * @property ScalarExpression $right
 * @property bool             $negated set to true for IS NOT DISTINCT FROM expressions
 */
class IsDistinctFromExpression extends GenericNode implements ScalarExpression
{
    /** @var ScalarExpression */
    protected $p_left;
    /** @var ScalarExpression */
    protected $p_right;
    /** @var bool */
    protected $p_negated = false;

    public function __construct(ScalarExpression $left, ScalarExpression $right, bool $negated = false)
    {
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for left and right operands");
        }

        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->p_negated = $negated;
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function setRight(ScalarExpression $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function setNegated(bool $negated): void
    {
        $this->p_negated = $negated;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsDistinctFromExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IS;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
