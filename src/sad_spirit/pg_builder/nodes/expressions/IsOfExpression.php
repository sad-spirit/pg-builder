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

use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    TypeName,
    lists\TypeList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an IS [NOT] OF expression
 *
 * Cannot be an OperatorExpression due to specific right operand
 *
 * @psalm-property TypeList $right
 *
 * @property ScalarExpression    $left
 * @property TypeList|TypeName[] $right
 */
class IsOfExpression extends NegatableExpression
{
    /** @var ScalarExpression */
    protected $p_left;
    /** @var TypeList */
    protected $p_right;

    public function __construct(ScalarExpression $left, TypeList $right, bool $not = false)
    {
        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->p_not = $not;
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsOfExpression($this);
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
