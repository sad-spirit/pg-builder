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
    GenericNode,
    ScalarExpression,
    lists\TypeList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an IS [NOT] OF expression
 *
 * Cannot be an OperatorExpression due to specific right operand
 *
 * @property ScalarExpression $left
 * @property TypeList         $right
 * @property bool             $not
 */
class IsOfExpression extends GenericNode implements ScalarExpression
{
    public function __construct(ScalarExpression $left, TypeList $right, bool $not = false)
    {
        $this->setNamedProperty('left', $left);
        $this->setNamedProperty('right', $right);
        $this->setNot($not);
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setNamedProperty('left', $left);
    }

    public function setNot(bool $not): void
    {
        $this->setNamedProperty('not', $not);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsOfExpression($this);
    }
}
