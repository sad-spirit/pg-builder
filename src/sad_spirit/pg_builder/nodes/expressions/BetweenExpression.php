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

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
};

/**
 * AST node representing [NOT] BETWEEN expression
 *
 * @property      ScalarExpression $argument
 * @property      ScalarExpression $left
 * @property      ScalarExpression $right
 * @property-read string           $operator
 */
class BetweenExpression extends GenericNode implements ScalarExpression
{
    public const BETWEEN                = 'between';
    public const BETWEEN_SYMMETRIC      = 'between symmetric';
    public const BETWEEN_ASYMMETRIC     = 'between asymmetric';
    public const NOT_BETWEEN            = 'not between';
    public const NOT_BETWEEN_SYMMETRIC  = 'not between symmetric';
    public const NOT_BETWEEN_ASYMMETRIC = 'not between asymmetric';

    private const ALLOWED_OPERATORS = [
        self::BETWEEN                => true,
        self::BETWEEN_SYMMETRIC      => true,
        self::BETWEEN_ASYMMETRIC     => true,
        self::NOT_BETWEEN            => true,
        self::NOT_BETWEEN_SYMMETRIC  => true,
        self::NOT_BETWEEN_ASYMMETRIC => true
    ];

    public function __construct(
        ScalarExpression $argument,
        ScalarExpression $left,
        ScalarExpression $right,
        string $operator = self::BETWEEN
    ) {
        if (!isset(self::ALLOWED_OPERATORS[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for BETWEEN-style expression");
        }
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('left', $left);
        $this->setNamedProperty('right', $right);
        $this->props['operator'] = (string)$operator;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setNamedProperty('argument', $argument);
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
        return $walker->walkBetweenExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_BETWEEN;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
