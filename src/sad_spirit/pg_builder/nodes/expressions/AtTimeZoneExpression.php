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

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo AT TIME ZONE bar" expression
 *
 * @property ScalarExpression $left
 * @property ScalarExpression $right
 */
class AtTimeZoneExpression extends GenericNode implements ScalarExpression
{
    public function __construct(ScalarExpression $left, ScalarExpression $right)
    {
        $this->setLeft($left);
        $this->setRight($right);
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setNamedProperty('left', $left);
    }

    public function setRight(ScalarExpression $right): void
    {
        $this->setNamedProperty('right', $right);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkAtTimeZoneExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_TIME_ZONE;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}