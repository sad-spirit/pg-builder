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

    private static $needsQuoting = [];

    public function __construct($tokenOrValue)
    {
        if ($tokenOrValue instanceof Token) {
            if (0 !== ((Token::TYPE_IDENTIFIER | Token::TYPE_KEYWORD) & $tokenOrValue->getType())) {
                $this->props['value'] = $tokenOrValue->getValue();
            } else {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an identifier or keyword token, %s given',
                    __CLASS__,
                    Token::typeToString($tokenOrValue->getType())
                ));
            }
        } elseif (
            is_scalar($tokenOrValue)
            || is_object($tokenOrValue) && method_exists($tokenOrValue, '__toString')
        ) {
            $this->props['value'] = (string)$tokenOrValue;
        } else {
            throw new InvalidArgumentException(sprintf(
                '%s requires either an instance of Token or value convertible to string, %s given',
                __CLASS__,
                is_object($tokenOrValue) ? 'object(' . get_class($tokenOrValue) . ')' : gettype($tokenOrValue)
            ));
        }
    }

    /**
     * Returns the string representation of the identifier, possibly with double quotes added
     *
     * @return string
     */
    public function __toString()
    {
        $value = $this->props['value'];
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
