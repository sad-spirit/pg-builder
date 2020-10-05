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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an expression from ORDER BY clause
 *
 * @property      ScalarExpression              $expression
 * @property-read string|null                   $direction
 * @property-read string|null                   $nullsOrder
 * @property-read string|QualifiedOperator|null $operator
 */
class OrderByElement extends GenericNode
{
    public const ASC         = 'asc';
    public const DESC        = 'desc';
    public const USING       = 'using';
    public const NULLS_FIRST = 'first';
    public const NULLS_LAST  = 'last';

    private const ALLOWED_DIRECTIONS = [
        self::ASC   => true,
        self::DESC  => true,
        self::USING => true
    ];

    private const ALLOWED_NULLS = [
        self::NULLS_FIRST => true,
        self::NULLS_LAST  => true
    ];

    public function __construct(
        ScalarExpression $expression,
        ?string $direction = null,
        ?string $nullsOrder = null,
        $operator = null
    ) {
        if (null !== $direction && !isset(self::ALLOWED_DIRECTIONS[$direction])) {
            throw new InvalidArgumentException("Unknown sort direction '{$direction}'");
        } elseif (self::USING === $direction && null === $operator) {
            throw new InvalidArgumentException("Operator required for USING sort direction");
        }
        if (null !== $nullsOrder && !isset(self::ALLOWED_NULLS[$nullsOrder])) {
            throw new InvalidArgumentException("Unknown nulls order '{$nullsOrder}'");
        }
        if (null !== $operator && !is_string($operator) && !$operator instanceof QualifiedOperator) {
            throw new InvalidArgumentException(sprintf(
                '%s requires either a string or an instance of QualifiedOperator for USING, %s given',
                __CLASS__,
                is_object($operator) ? 'object(' . get_class($operator) . ')' : gettype($operator)
            ));
        }

        $this->setNamedProperty('expression', $expression);
        $this->props['direction']  = $direction;
        $this->props['nullsOrder'] = $nullsOrder;
        $this->props['operator']   = $operator;
    }

    public function setExpression(ScalarExpression $expression): void
    {
        $this->setNamedProperty('expression', $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOrderByElement($this);
    }
}
