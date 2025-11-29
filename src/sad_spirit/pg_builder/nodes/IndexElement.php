<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    TreeWalker,
    enums\IndexElementDirection,
    enums\NullsOrder,
    exceptions\InvalidArgumentException
};

/**
 * AST node representing a column description in CREATE INDEX statement
 *
 * We don't parse `CREATE INDEX` statements, but the same syntax is also used in `ON CONFLICT`
 * clauses of `INSERT` statements, and we do parse those.
 *
 * @property      ScalarExpression|Identifier $expression
 * @property-read QualifiedName|null          $collation
 * @property-read QualifiedName|null          $opClass
 * @property-read IndexElementDirection|null  $direction
 * @property-read NullsOrder|null             $nullsOrder
 */
class IndexElement extends GenericNode
{
    /** @internal Maps to `$expression` magic property, use the latter instead */
    protected Identifier|ScalarExpression $p_expression;
    /** @internal Maps to `$collation` magic property, use the latter instead */
    protected ?QualifiedName $p_collation;
    /** @internal Maps to `$opClass` magic property, use the latter instead */
    protected ?QualifiedName $p_opClass;
    /** @internal Maps to `$direction` magic property, use the latter instead */
    protected ?IndexElementDirection $p_direction;
    /** @internal Maps to `$nullsOrder` magic property, use the latter instead */
    protected ?NullsOrder $p_nullsOrder;

    public function __construct(
        ScalarExpression|Identifier $expression,
        ?QualifiedName $collation = null,
        ?QualifiedName $opClass = null,
        ?IndexElementDirection $direction = null,
        ?NullsOrder $nullsOrder = null
    ) {
        if (null !== $collation && $collation === $opClass) {
            throw new InvalidArgumentException("Cannot use the same Node for collation and opClass");
        }

        $this->generatePropertyNames();

        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);

        $this->setProperty($this->p_collation, $collation);
        $this->setProperty($this->p_opClass, $opClass);

        $this->p_direction  = $direction;
        $this->p_nullsOrder = $nullsOrder;
    }

    /**
     * Sets the node identifying the indexed column / function call / expression
     *
     * @internal Support method for `$expression` magic property, use the property instead
     */
    public function setExpression(ScalarExpression|Identifier $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkIndexElement($this);
    }
}
