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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a column description in CREATE INDEX statement
 *
 * We don't parse CREATE INDEX statements, but the same syntax is also used in ON CONFLICT
 * clauses of INSERT statements and we do parse those.
 *
 * @property      ScalarExpression|Identifier $expression
 * @property-read QualifiedName|null          $collation
 * @property-read QualifiedName|null          $opClass
 * @property-read string|null                 $direction
 * @property-read string|null                 $nullsOrder
 */
class IndexElement extends Node
{
    public function __construct(
        $expression, QualifiedName $collation = null, QualifiedName $opClass = null,
        $direction = null, $nullsOrder = null
    ) {
        if (null !== $direction && !in_array($direction, array('asc', 'desc'), true)) {
            throw new InvalidArgumentException("Unknown sort direction '{$direction}'");
        }
        if (null !== $nullsOrder && !in_array($nullsOrder, array('first', 'last'), true)) {
            throw new InvalidArgumentException("Unknown nulls order '{$nullsOrder}'");
        }
        $this->setExpression($expression);

        $this->setNamedProperty('collation', $collation);
        $this->setNamedProperty('opClass', $opClass);
        $this->props['direction']  = $direction;
        $this->props['nullsOrder'] = $nullsOrder;
    }

    public function setExpression($expression)
    {
        if (!($expression instanceof ScalarExpression) && !($expression instanceof Identifier)) {
            throw new InvalidArgumentException(sprintf(
                'IndexElement needs either a ScalarExpression or column Identifier as its expression, %s given',
                is_object($expression) ? 'object(' . get_class($expression) . ')' : gettype($expression)
            ));
        }
        $this->setNamedProperty('expression', $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIndexElement($this);
    }
}