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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\{
    Keywords,
    Token,
    TreeWalker,
    exceptions\InvalidArgumentException
};

/**
 * Represents an identifier (e.g. column name or field name)
 *
 * @property-read string $value
 */
class Identifier extends GenericNode
{
    use NonRecursiveNode;

    /** @var string */
    protected $p_value;

    protected $propertyNames = [
        'value' => 'p_value'
    ];

    /**
     * Cache of check results ["identifier value" => "needs double quotes?"]
     * @var array<string, bool>
     */
    private static $needsQuoting = [];

    public function __construct(string $value)
    {
        if ('' === $value) {
            throw new InvalidArgumentException("Identifier cannot be an empty string");
        }
        $this->p_value = $value;
    }

    /**
     * Creates an instance of Identifier from identifier or keyword Token
     *
     * @param Token $token
     * @return self
     */
    public static function createFromToken(Token $token): self
    {
        if (0 === ((Token::TYPE_IDENTIFIER | Token::TYPE_KEYWORD) & $token->getType())) {
            throw new InvalidArgumentException(sprintf(
                '%s requires an identifier or keyword token, %s given',
                __CLASS__,
                Token::typeToString($token->getType())
            ));
        }

        return new self($token->getValue());
    }

    /**
     * As Identifier cannot have any child Nodes, this just removes link to parent node
     */
    public function __clone()
    {
        $this->parentNode = null;
    }

    /**
     * Serialized representation of Identifier is its value property
     * @return string
     */
    public function serialize(): string
    {
        return $this->p_value;
    }

    /**
     * Serialized representation of Identifier is its value property
     * @return array
     */
    public function __serialize(): array
    {
        return [$this->p_value];
    }

    /**
     * Sets the value property from given string
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->p_value = $serialized;
    }

    /**
     * Sets the value property from given array
     * @param array $data
     */
    public function __unserialize(array $data): void
    {
        [$this->p_value] = $data;
    }

    /**
     * Returns the string representation of the identifier, possibly with double quotes added
     *
     * @return string
     */
    public function __toString()
    {
        $value = $this->p_value;
        // We are likely to see the same identifier again, so cache the check results
        if (!isset(self::$needsQuoting[$value])) {
            self::$needsQuoting[$value] = !preg_match('/^[a-z_][a-z_0-9\$]*$/D', $value)
                                          || Keywords::isKeyword($value);
        }
        return self::$needsQuoting[$value]
               ? '"' . str_replace('"', '""', $value) . '"'
               : $value;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIdentifier($this);
    }
}
