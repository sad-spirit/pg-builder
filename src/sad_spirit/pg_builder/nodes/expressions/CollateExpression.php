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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\QualifiedName,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing a "foo COLLATE bar" expression
 *
 * @property      ScalarExpression $argument
 * @property-read QualifiedName    $collation
 */
class CollateExpression extends GenericNode implements ScalarExpression
{
    public function __construct(protected ScalarExpression $p_argument, protected QualifiedName $p_collation)
    {
        $this->generatePropertyNames();
        $this->p_argument->setParentNode($this);
        $this->p_collation->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkCollateExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_COLLATE;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
