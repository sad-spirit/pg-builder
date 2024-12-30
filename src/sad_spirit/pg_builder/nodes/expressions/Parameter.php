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
     *
     * @param Token $token
     * @return self
     */
    public static function createFromToken(Token $token): self
    {
        if (!$token->matches(TokenType::PARAMETER)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects a parameter token, %s given',
                __CLASS__,
                $token->getType()->toString()
            ));
        }

        if (TokenType::POSITIONAL_PARAM === $token->getType()) {
            return new PositionalParameter((int)$token->getValue());
        } else {
            return new NamedParameter($token->getValue());
        }
    }

    public function __clone()
    {
        $this->parentNode = null;
    }
}
