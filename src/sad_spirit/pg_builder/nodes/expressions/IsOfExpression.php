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
use sad_spirit\pg_builder\nodes\lists\TypeList;

/**
 * AST node representing an IS [NOT] OF expression
 *
 * Cannot be an OperatorExpression due to specific right operand
 *
 * @property      ScalarExpression $left
 * @property      TypeList         $right
 * @property-read string           $operator
 */
class IsOfExpression extends GenericNode implements ScalarExpression
{
    public const IS_OF     = 'is of';
    public const IS_NOT_OF = 'is not of';

    private const ALLOWED_OPERATORS = [
        self::IS_OF     => true,
        self::IS_NOT_OF => true
    ];

    public function __construct(ScalarExpression $left, TypeList $right, string $operator = self::IS_OF)
    {
        if (!isset(self::ALLOWED_OPERATORS[$operator])) {
            throw new InvalidArgumentException("Unknown operator '{$operator}' for IS OF-style expression");
        }
        $this->setNamedProperty('left', $left);
        $this->setNamedProperty('right', $right);
        $this->props['operator'] = $operator;
    }

    public function setLeft(ScalarExpression $left): void
    {
        $this->setNamedProperty('left', $left);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsOfExpression($this);
    }
}
