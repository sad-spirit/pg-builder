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
    Lexer,
    Token,
    TreeWalker,
    exceptions\InvalidArgumentException,
    exceptions\SyntaxException
};

/**
 * Represents the OPERATOR(...) construct with possibly specified catalog and schema names
 *
 * @property-read Identifier|null $catalog
 * @property-read Identifier|null $schema
 * @property-read string          $operator
 */
class QualifiedOperator extends GenericNode
{
    use LeafNode;

    /** @noinspection PhpMissingBreakStatementInspection */
    public function __construct(...$nameParts)
    {
        $this->props = [
            'catalog'  => null,
            'schema'   => null,
            'operator' => null
        ];

        switch (count($nameParts)) {
            case 3:
                $this->setNamedProperty('catalog', $this->expectIdentifier(array_shift($nameParts), 'catalog'));
                // fall-through is intentional
            case 2:
                $this->setNamedProperty('schema', $this->expectIdentifier(array_shift($nameParts), 'schema'));
                // fall-through is intentional
            case 1:
                $this->setNamedProperty('operator', $this->expectOperator(array_shift($nameParts)));
                break;
            case 0:
                throw new InvalidArgumentException(__CLASS__ . ' expects operator name parts, none given');
            default:
                throw new SyntaxException("Too many dots in qualified name: " . implode('.', $nameParts));
        }
    }

    /**
     * Tries to convert part of operator name to Identifier
     *
     * @param mixed $namePart
     * @param string $index
     * @return Identifier
     */
    private function expectIdentifier($namePart, string $index): Identifier
    {
        if ($namePart instanceof Identifier) {
            return $namePart;
        }
        try {
            return new Identifier($namePart);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf(
                "%s: %s part of operator name could not be converter to Identifier; %s",
                __CLASS__,
                $index,
                $e->getMessage()
            ));
        }
    }

    /**
     * Ensures that the last part of the qualified name looks like an operator
     *
     * @param $namePart
     * @return string
     */
    private function expectOperator($namePart): string
    {
        if ($namePart instanceof Token) {
            if ($namePart->matches(Token::TYPE_SPECIAL)) {
                $operator = $namePart->getValue();
            } else {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an operator token, %s given',
                    __CLASS__,
                    Token::typeToString($namePart->getType())
                ));
            }
        } elseif (
            is_string($namePart)
            || is_object($namePart) && method_exists($namePart, '__toString')
        ) {
            $operator = (string)$namePart;
        } else {
            throw new InvalidArgumentException(sprintf(
                '%s requires either an instance of Token or value convertible to string, %s given',
                __CLASS__,
                is_object($namePart) ? 'object(' . get_class($namePart) . ')' : gettype($namePart)
            ));
        }

        if (strlen($operator) !== strspn($operator, Lexer::CHARS_OPERATOR)) {
            throw new SyntaxException(sprintf(
                "%s: '%s' does not look like a valid operator string",
                __CLASS__,
                $operator
            ));
        }

        return $operator;
    }

    /**
     * Returns the string representation of the node, with double quotes added as needed
     *
     * @return string
     */
    public function __toString()
    {
        return 'operator('
            . (null === $this->props['catalog'] ? '' : (string)$this->props['catalog'] . '.')
            . (null === $this->props['schema'] ? '' : (string)$this->props['schema'] . '.')
            . (string)$this->props['operator'] . ')';
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkQualifiedOperator($this);
    }
}