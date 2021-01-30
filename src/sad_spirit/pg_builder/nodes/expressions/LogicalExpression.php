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
    nodes\HasBothPropsAndOffsets,
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\lists\ExpressionList;

/**
 * AST node representing a group of expressions combined by AND or OR operators
 *
 * @property-read string $operator
 */
class LogicalExpression extends ExpressionList implements ScalarExpression
{
    use HasBothPropsAndOffsets;

    public const AND = 'and';
    public const OR  = 'or';

    private const PRECEDENCES = [
        self::AND => self::PRECEDENCE_AND,
        self::OR  => self::PRECEDENCE_OR
    ];

    /** @var string */
    protected $p_operator = self::AND;

    /**
     * LogicalExpression constructor
     *
     * @param null|string|iterable<ScalarExpression> $terms
     * @param string                                 $operator
     */
    public function __construct($terms = null, string $operator = self::AND)
    {
        if (!isset(self::PRECEDENCES[$operator])) {
            throw new InvalidArgumentException("Unknown logical operator '{$operator}'");
        }
        $this->generatePropertyNames();
        parent::__construct($terms);
        $this->p_operator = $operator;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkLogicalExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCES[$this->p_operator];
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
