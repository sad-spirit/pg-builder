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

namespace sad_spirit\pg_builder;

/**
 * Class representing a token
 */
class Token
{
    /* Generic types */
    public const TYPE_LITERAL                = 1 << 8;
    public const TYPE_PARAMETER              = 1 << 9;
    public const TYPE_SPECIAL                = 1 << 10;
    public const TYPE_IDENTIFIER             = 1 << 11;
    public const TYPE_KEYWORD                = 1 << 12;

    /* Literal types */
    public const TYPE_STRING                 = self::TYPE_LITERAL | 1 << 0;
    public const TYPE_BINARY_STRING          = self::TYPE_LITERAL | 1 << 1;
    public const TYPE_HEX_STRING             = self::TYPE_LITERAL | 1 << 2;
    public const TYPE_NCHAR_STRING           = self::TYPE_LITERAL | 1 << 3; // I think this is just noise right now, behaves as simple string
    public const TYPE_INTEGER                = self::TYPE_LITERAL | 1 << 4;
    public const TYPE_FLOAT                  = self::TYPE_LITERAL | 1 << 5;

    /* Parameter types */
    public const TYPE_POSITIONAL_PARAM       = self::TYPE_PARAMETER | 1 << 0;
    public const TYPE_NAMED_PARAM            = self::TYPE_PARAMETER | 1 << 1;

    /* Special characters and operators */
    public const TYPE_SPECIAL_CHAR           = self::TYPE_SPECIAL | 1 << 0;
    public const TYPE_TYPECAST               = self::TYPE_SPECIAL | 1 << 1;
    public const TYPE_COLON_EQUALS           = self::TYPE_SPECIAL | 1 << 2;
    public const TYPE_OPERATOR               = self::TYPE_SPECIAL | 1 << 3;
    public const TYPE_INEQUALITY             = self::TYPE_SPECIAL | 1 << 4;
    public const TYPE_EQUALS_GREATER         = self::TYPE_SPECIAL | 1 << 5;

    /* Keywords, as in src/include/parser/keywords.h */
    public const TYPE_UNRESERVED_KEYWORD     = self::TYPE_KEYWORD | 1 << 0;
    public const TYPE_COL_NAME_KEYWORD       = self::TYPE_KEYWORD | 1 << 1;
    public const TYPE_TYPE_FUNC_NAME_KEYWORD = self::TYPE_KEYWORD | 1 << 2;
    public const TYPE_RESERVED_KEYWORD       = self::TYPE_KEYWORD | 1 << 3;

    /**
     * Signals end of input
     */
    public const TYPE_EOF                    = 1 << 16;

    protected $type;
    protected $value;
    protected $position;

    /**
     * Constructor.
     *
     * @param integer $type     Token type, one of TYPE_* constants
     * @param string  $value    Token value
     * @param integer $position Position of token in the source
     */
    public function __construct($type, $value, $position)
    {
        $this->type     = $type;
        $this->value    = $value;
        $this->position = $position;
    }

    /**
     * Returns a string representation of the token.
     *
     * @return string
     */
    public function __toString()
    {
        if (self::TYPE_EOF === $this->type) {
            return self::typeToString($this->type);
        }
        return sprintf(
            "%s '%s' at position %d",
            self::typeToString($this->type), $this->value, $this->position
        );
    }

    /**
     * Checks whether current token matches given type and/or value
     *
     * Possible parameters
     * * type and value (or array of possible values)
     * * just type ($type is integer, $values is null)
     * * just value ($type is not integer, $values is null) - token will be tested
     *   with TYPE_KEYWORD and TYPE_SPECIAL
     *
     * @param array|string|integer $type
     * @param array|string|null    $values
     * @return bool
     */
    public function matches($type, $values = null)
    {
        if (null === $values && !is_int($type)) {
            return $this->matches(self::TYPE_KEYWORD, $type)
                   || $this->matches(self::TYPE_SPECIAL, $type);
        }

        return ($type & $this->type) === $type
               && (null === $values
                   || (is_array($values) && in_array($this->value, $values))
                   || $this->value == $values);
    }

    /**
     * Returns token's position in input string
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Returns token's type
     *
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns token's value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns human readable representation of a token type
     *
     * @param int $type
     * @return string
     * @throws exceptions\InvalidArgumentException
     */
    public static function typeToString($type)
    {
        switch ($type) {
        case self::TYPE_EOF:
            return 'end of input';
        case self::TYPE_STRING:
            return 'string literal';
        case self::TYPE_BINARY_STRING:
            return 'binary string literal';
        case self::TYPE_HEX_STRING:
            return 'hexadecimal string literal';
        case self::TYPE_NCHAR_STRING:
            return 'nchar string literal';
        case self::TYPE_INTEGER:
            return 'integer literal';
        case self::TYPE_FLOAT:
            return 'numeric literal';
        case self::TYPE_POSITIONAL_PARAM:
            return 'positional parameter';
        case self::TYPE_NAMED_PARAM:
            return 'named parameter';
        case self::TYPE_OPERATOR:
            return 'operator';
        case self::TYPE_TYPECAST:
            return 'typecast operator';
        case self::TYPE_COLON_EQUALS:
        case self::TYPE_EQUALS_GREATER:
            return 'named argument mark';
        case self::TYPE_SPECIAL_CHAR:
            return 'special character';
        case self::TYPE_INEQUALITY:
            return 'comparison operator';
        case self::TYPE_IDENTIFIER:
            return 'identifier';
        default:
            if ($type & self::TYPE_KEYWORD) {
                return 'keyword';
            }
        }
        throw new exceptions\InvalidArgumentException("Unknown token type '{$type}'");
    }
}