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
    nodes\FunctionLike,
    nodes\GenericNode,
    nodes\TypeName,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing a conversion of some value to a given datatype
 *
 * All possible type casting expressions are represented by this node:
 *  * CAST(foo as bar)
 *  * foo::bar
 *  * bar 'string constant'
 *
 * @property      ScalarExpression $argument
 * @property-read TypeName         $type
 */
class TypecastExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    protected ?ScalarExpression $p_argument = null;

    public function __construct(ScalarExpression $argument, protected TypeName $p_type)
    {
        $this->generatePropertyNames();

        $this->setArgument($argument);
        $this->p_type->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        if (null !== $this->p_argument) {
            $this->setRequiredProperty($this->p_argument, $argument);
        } else {
            $this->p_argument = $argument;
            $this->p_argument->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkTypecastExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_TYPECAST;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
