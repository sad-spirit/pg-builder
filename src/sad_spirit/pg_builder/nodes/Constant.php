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
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a constant (a literal value)
 *
 * @property-read integer $type  Type of constant, one of Token::TYPE_* constants
 * @property-read string  $value String value of constant
 */
class Constant extends Node implements ScalarExpression
{
    public function __construct($tokenOrConstant)
    {
        if ($tokenOrConstant instanceof Token) {
            if (0 === (Token::TYPE_LITERAL & $tokenOrConstant->getType())
                && !$tokenOrConstant->matches(Token::TYPE_KEYWORD, array('null', 'false', 'true'))
            ) {
                throw new InvalidArgumentException(sprintf(
                    '%s requires a literal token, %s given',
                    __CLASS__, Token::typeToString($tokenOrConstant->getType())
                ));
            }
            $this->props['type']  = $tokenOrConstant->getType();
            $this->props['value'] = $tokenOrConstant->getValue();

        } elseif (is_null($tokenOrConstant)) {
            $this->props['type']  = Token::TYPE_RESERVED_KEYWORD;
            $this->props['value'] = 'null';

        } elseif (!is_scalar($tokenOrConstant)) {
            throw new InvalidArgumentException(sprintf(
                '%s requires a Token instance or a scalar value, %s given',
                __CLASS__, is_object($tokenOrConstant) ? 'object(' . get_class($tokenOrConstant) . ')'
                           : gettype($tokenOrConstant)
            ));

        } elseif (is_bool($tokenOrConstant)) {
            $this->props['type']  = Token::TYPE_RESERVED_KEYWORD;
            $this->props['value'] = $tokenOrConstant ? 'true' : 'false';

        } elseif (is_float($tokenOrConstant)) {
            $this->props['type']  = Token::TYPE_FLOAT;
            $this->props['value'] = str_replace(',', '.', (string)$tokenOrConstant);

        } elseif (is_int($tokenOrConstant)) {
            $this->props['type']  = Token::TYPE_INTEGER;
            $this->props['value'] = (string)$tokenOrConstant;

        } else {
            $this->props['type']  = Token::TYPE_STRING;
            $this->props['value'] = $tokenOrConstant;
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkConstant($this);
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