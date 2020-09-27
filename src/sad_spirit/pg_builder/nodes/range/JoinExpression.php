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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\lists\IdentifierList;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for JOIN expression in FROM clause
 *
 * @property      FromElement           $left
 * @property      FromElement           $right
 * @property-read string                $type
 * @property      bool                  $natural
 * @property      IdentifierList|null   $using
 * @property      ScalarExpression|null $on
 */
class JoinExpression extends FromElement
{
    protected static $allowedTypes = [
        'cross' => true,
        'left'  => true,
        'right' => true,
        'full'  => true,
        'inner' => true
    ];

    public function __construct(FromElement $left, FromElement $right, $joinType = 'inner')
    {
        if (!isset(self::$allowedTypes[$joinType])) {
            throw new InvalidArgumentException("Unknown join type '{$joinType}'");
        }

        $this->setLeft($left);
        $this->setRight($right);
        $this->props = array_merge($this->props, [
            'type'    => $joinType,
            'natural' => null,
            'using'   => null,
            'on'      => null
        ]);
    }

    public function setLeft(FromElement $left)
    {
        $this->setNamedProperty('left', $left);
    }

    public function setRight(FromElement $right)
    {
        $this->setNamedProperty('right', $right);
    }

    public function setNatural($natural)
    {
        if ($natural) {
            if ('cross' === $this->props['type']) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif (!empty($this->props['using']) || !empty($this->props['on'])) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
        }
        $this->props['natural'] = (bool)$natural;
    }

    public function setUsing($using = null)
    {
        if (null !== $using) {
            if ('cross' === $this->props['type']) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif (!empty($this->props['natural']) || !empty($this->props['on'])) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
            if (is_string($using)) {
                if (!($parser = $this->getParser())) {
                    throw new InvalidArgumentException("Passed a string for a USING clause without a Parser available");
                }
                $using = $parser->parseColIdList($using);
            } elseif (is_array($using)) {
                $using = new IdentifierList($using);
            }
            if (!($using instanceof IdentifierList)) {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an SQL string, an array of identifiers or an instance of IdentifierList, %s given',
                    __METHOD__,
                    is_object($using) ? 'object(' . get_class($using) . ')' : gettype($using)
                ));
            }
        }
        $this->setNamedProperty('using', $using);
    }

    public function setOn($on = null)
    {
        if (null !== $on) {
            if ('cross' === $this->props['type']) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif (!empty($this->props['natural']) || !empty($this->props['using'])) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
            if (is_string($on)) {
                if (!($parser = $this->getParser())) {
                    throw new InvalidArgumentException(
                        'Passed a string for an ON expression without a Parser available'
                    );
                }
                $on = $parser->parseExpression($on);
            }
            if (!($on instanceof ScalarExpression)) {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an SQL expression string or an instance of ScalarExpression, %s given',
                    __METHOD__,
                    is_object($on) ? 'object(' . get_class($on) . ')' : gettype($on)
                ));
            }
        }
        $this->setNamedProperty('on', $on);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJoinExpression($this);
    }
}
