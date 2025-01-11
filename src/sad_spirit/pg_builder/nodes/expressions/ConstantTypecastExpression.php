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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    NonRecursiveNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\enums\StringConstantType;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Node for type cast used in the context of AexprConst, this can only take the form of "type.name 'a string value'"
 *
 * Currently, this Node is only used by CycleClause, where values for $markColumn are defined as AexprConstant in
 * the grammar.
 *
 * @property StringConstant $argument
 */
class ConstantTypecastExpression extends TypecastExpression
{
    use ExpressionAtom;
    use NonRecursiveNode;

    public function setArgument(ScalarExpression $argument): void
    {
        if (!$argument instanceof StringConstant || StringConstantType::CHARACTER !== $argument->type) {
            throw new InvalidArgumentException(\sprintf(
                "%s only allows a generic string constant as an argument, %s given",
                self::class,
                $argument instanceof StringConstant
                ? (StringConstantType::HEXADECIMAL === $argument->type ? 'hexadecimal' : 'binary') . ' string'
                : $argument::class
            ));
        }
        parent::setArgument($argument);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkConstantTypecastExpression($this);
    }
}
