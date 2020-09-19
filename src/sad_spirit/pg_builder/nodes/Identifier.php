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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\Token,
    sad_spirit\pg_builder\TreeWalker,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Represents an identifier (e.g. column name or field name)
 *
 * @property-read string $value
 */
class Identifier extends Node
{
    public function __construct($tokenOrValue)
    {
        if ($tokenOrValue instanceof Token) {
            if (Token::TYPE_IDENTIFIER !== $tokenOrValue->getType()
                && 0 === (Token::TYPE_KEYWORD & $tokenOrValue->getType())
            ) {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an identifier or keyword token, %s given',
                    __CLASS__, Token::typeToString($tokenOrValue->getType())
                ));
            }
            $this->props['value'] = $tokenOrValue->getValue();

        } else {
            $this->props['value'] = (string)$tokenOrValue;
        }
    }

    /**
     * This is only used for constructing exception messages
     *
     * @return string
     */
    public function __toString()
    {
        return '"' . str_replace('"', '""', $this->props['value']) . '"';
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIdentifier($this);
    }

    /**
     * Checks in base setParentNode() are redundant as this can only be a leaf node
     *
     * @param Node $parent
     */
    protected function setParentNode(Node $parent = null)
    {
        if ($parent && $this->parentNode && $parent !== $this->parentNode) {
            $this->parentNode->removeChild($this);
        }
        $this->parentNode = $parent;
    }
}
