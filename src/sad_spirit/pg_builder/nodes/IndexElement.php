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
use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\TreeWalker;

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
class IndexElement extends GenericNode
{
    public const ASC         = 'asc';
    public const DESC        = 'desc';
    public const NULLS_FIRST = 'first';
    public const NULLS_LAST  = 'last';

    private const ALLOWED_DIRECTIONS = [
        self::ASC  => true,
        self::DESC => true
    ];

    private const ALLOWED_NULLS = [
        self::NULLS_FIRST => true,
        self::NULLS_LAST  => true
    ];

    /** @var ScalarExpression|Identifier */
    protected $p_expression;
    /** @var QualifiedName|null */
    protected $p_collation;
    /** @var QualifiedName|null */
    protected $p_opClass;
    /** @var string|null */
    protected $p_direction;
    /** @var string|null */
    protected $p_nullsOrder;

    /**
     * IndexElement constructor
     *
     * @param ScalarExpression|Identifier $expression
     * @param QualifiedName|null          $collation
     * @param QualifiedName|null          $opClass
     * @param string|null                 $direction
     * @param string|null                 $nullsOrder
     */
    public function __construct(
        Node $expression,
        QualifiedName $collation = null,
        QualifiedName $opClass = null,
        ?string $direction = null,
        ?string $nullsOrder = null
    ) {
        if (null !== $direction && !isset(self::ALLOWED_DIRECTIONS[$direction])) {
            throw new InvalidArgumentException("Unknown sort direction '{$direction}'");
        }
        if (null !== $nullsOrder && !isset(self::ALLOWED_NULLS[$nullsOrder])) {
            throw new InvalidArgumentException("Unknown nulls order '{$nullsOrder}'");
        }

        $this->generatePropertyNames();
        $this->setExpression($expression);

        $this->setProperty($this->p_collation, $collation);
        $this->setProperty($this->p_opClass, $opClass);
        $this->p_direction  = $direction;
        $this->p_nullsOrder = $nullsOrder;
    }

    /**
     * Sets the node identifying the indexed column / function call / expression
     *
     * @param ScalarExpression|Identifier $expression
     */
    public function setExpression(Node $expression): void
    {
        if (!($expression instanceof ScalarExpression) && !($expression instanceof Identifier)) {
            throw new InvalidArgumentException(sprintf(
                'IndexElement needs either a ScalarExpression or column Identifier as its expression, %s given',
                get_class($expression)
            ));
        }
        $this->setProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIndexElement($this);
    }
}
