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

use sad_spirit\pg_builder\{
    Token,
    TokenType,
    enums\ConstantName,
    enums\StringConstantType,
    exceptions\InvalidArgumentException,
    tokens\KeywordToken
};
use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    GenericNode,
    NonRecursiveNode,
    ScalarExpression
};

/**
 * Abstract base class for nodes representing a constant (a literal value)
 */
abstract class Constant extends GenericNode implements ScalarExpression
{
    use NonRecursiveNode;
    use ExpressionAtom;

    public function __construct(public readonly string $value)
    {
    }

    /**
     * Creates an instance of proper Constant subclass based on given Token
     */
    public static function createFromToken(Token $token): self
    {
        if (
            $token instanceof KeywordToken
            && (null !== $name = ConstantName::tryFromKeywords($token->getKeyword()))
        ) {
            return new KeywordConstant($name);
        }

        if ($token->matches(TokenType::LITERAL)) {
            return match ($token->getType()) {
                TokenType::INTEGER, TokenType::FLOAT => new NumericConstant($token->getValue()),
                TokenType::BINARY_STRING => new StringConstant($token->getValue(), StringConstantType::BINARY),
                TokenType::HEX_STRING => new StringConstant($token->getValue(), StringConstantType::HEXADECIMAL),
                default => new StringConstant($token->getValue()),
            };
        }

        throw new InvalidArgumentException(sprintf(
            '%s requires a literal token, %s given',
            self::class,
            $token->getType()->toString()
        ));
    }

    /**
     * Creates an instance of proper Constant subclass based on PHP value
     */
    public static function createFromPHPValue(mixed $value): self
    {
        return match (\gettype($value)) {
            'NULL' => new KeywordConstant(ConstantName::NULL),
            'boolean' => new KeywordConstant($value ? ConstantName::TRUE : ConstantName::FALSE),
            'integer' => new NumericConstant((string)$value),
            'double' => new NumericConstant(\str_replace(',', '.', (string)$value)),
            'string' => new StringConstant($value),
            default => \is_object($value) && \method_exists($value, '__toString')
                ? new StringConstant((string)$value)
                : throw new InvalidArgumentException(\sprintf(
                    '%s() requires a scalar value or an object implementing __toString() method, %s given',
                    __METHOD__,
                    \is_object($value)
                        ? 'object(' . $value::class . ')'
                        : \gettype($value)
                ))
        };
    }

    public function __clone()
    {
        $this->parentNode = null;
    }

    public function __serialize(): array
    {
        return [$this->value];
    }

    public function __unserialize(array $data): void
    {
        [$this->value] = $data;
    }
}
