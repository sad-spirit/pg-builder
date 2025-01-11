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

use sad_spirit\pg_builder\Token;
use sad_spirit\pg_builder\TokenType;
use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    GenericNode,
    NonRecursiveNode,
    ScalarExpression
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Abstract base class for parameter nodes
 */
abstract class Parameter extends GenericNode implements ScalarExpression
{
    use NonRecursiveNode;
    use ExpressionAtom;

    /**
     * Creates an object of proper Parameter subclass based on given Token
     */
    public static function createFromToken(Token $token): self
    {
        return match ($token->getType()) {
            TokenType::POSITIONAL_PARAM => new PositionalParameter((int)$token->getValue()),
            TokenType::NAMED_PARAM => new NamedParameter($token->getValue()),
            default => throw new InvalidArgumentException(\sprintf(
                '%s expects a parameter token, %s given',
                self::class,
                $token->getType()->toString()
            ))
        };
    }

    public function __clone()
    {
        $this->parentNode = null;
    }
}
