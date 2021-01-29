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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\Token;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    GenericNode,
    NonRecursiveNode,
    ScalarExpression
};

/**
 * Abstract base class for nodes representing a constant (a literal value)
 *
 * @property-read string $value String value of constant
 */
abstract class Constant extends GenericNode implements ScalarExpression
{
    use NonRecursiveNode;
    use ExpressionAtom;

    /** @var string */
    protected $p_value;

    protected $propertyNames = [
        'value' => 'p_value'
    ];

    /**
     * Creates an instance of proper Constant subclass based on given Token
     *
     * @param Token $token
     * @return self
     */
    public static function createFromToken(Token $token): self
    {
        if ($token->matches(Token::TYPE_KEYWORD, ['null', 'false', 'true'])) {
            return new KeywordConstant($token->getValue());
        }

        if (0 !== (Token::TYPE_LITERAL & $token->getType())) {
            switch ($token->getType()) {
                case Token::TYPE_INTEGER:
                case Token::TYPE_FLOAT:
                    return new NumericConstant($token->getValue());

                case Token::TYPE_BINARY_STRING:
                    return new StringConstant($token->getValue(), StringConstant::TYPE_BINARY);

                case Token::TYPE_HEX_STRING:
                    return new StringConstant($token->getValue(), StringConstant::TYPE_HEXADECIMAL);

                default:
                    return new StringConstant($token->getValue());
            }
        }

        throw new InvalidArgumentException(sprintf(
            '%s requires a literal token, %s given',
            __CLASS__,
            Token::typeToString($token->getType())
        ));
    }

    /**
     * Creates an instance of proper Constant subclass based on PHP value
     *
     * @param mixed $value
     * @return self
     */
    public static function createFromPHPValue($value): self
    {
        switch (gettype($value)) {
            case 'NULL':
                return new KeywordConstant(KeywordConstant::NULL);

            case 'boolean':
                return new KeywordConstant($value ? KeywordConstant::TRUE : KeywordConstant::FALSE);

            case 'integer':
                return new NumericConstant((string)$value);

            case 'double':
                return new NumericConstant(str_replace(',', '.', (string)$value));

            case 'string':
                return new StringConstant($value);

            case 'object':
                if (method_exists($value, '__toString')) {
                    return new StringConstant((string)$value);
                }
        }
        throw new InvalidArgumentException(sprintf(
            '%s() requires a scalar value or an object implementing __toString() method, %s given',
            __METHOD__,
            is_object($value)
                ? 'object(' . get_class($value) . ')'
                : gettype($value)
        ));
    }

    public function __clone()
    {
        $this->parentNode = null;
    }

    public function serialize(): string
    {
        return $this->p_value;
    }

    public function unserialize($serialized)
    {
        $this->p_value = $serialized;
    }
}
