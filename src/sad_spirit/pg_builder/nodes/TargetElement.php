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

use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a part of target list for a statement
 *
 * @property      ScalarExpression $expression
 * @property-read Identifier|null  $alias
 */
class TargetElement extends GenericNode
{
    protected Identifier|null $p_alias = null;

    public function __construct(protected ScalarExpression $p_expression, ?Identifier $alias = null)
    {
        $this->generatePropertyNames();
        $this->p_expression->setParentNode($this);

        $this->setProperty($this->p_alias, $alias);
    }

    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkTargetElement($this);
    }
}
