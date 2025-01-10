<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
 * We don't parse CREATE INDEX statements, but the same syntax is also used in ON CONFLICT
 * clauses of INSERT statements, and we do parse those.
 *
 * @property      ScalarExpression|Identifier $expression
 * @property-read QualifiedName|null          $collation
 * @property-read QualifiedName|null          $opClass
 * @property-read IndexElementDirection|null  $direction
 * @property-read NullsOrder|null             $nullsOrder
 */
class IndexElement extends GenericNode
{
    protected ?QualifiedName $p_collation = null;
    protected ?QualifiedName $p_opClass = null;

    public function __construct(
        protected ScalarExpression|Identifier $p_expression,
        ?QualifiedName $collation = null,
        ?QualifiedName $opClass = null,
        protected ?IndexElementDirection $p_direction = null,
        protected ?NullsOrder $p_nullsOrder = null
    ) {
        if (null !== $collation && $collation === $opClass) {
            throw new InvalidArgumentException("Cannot use the same Node for collation and opClass");
        }

        $this->generatePropertyNames();
        $this->p_expression->setParentNode($this);

        $this->setProperty($this->p_collation, $collation);
        $this->setProperty($this->p_opClass, $opClass);
    }

    /**
     * Sets the node identifying the indexed column / function call / expression
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
