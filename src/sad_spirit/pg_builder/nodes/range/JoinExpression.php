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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    nodes\Identifier,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node for JOIN expression in FROM clause
 *
 * @psalm-property UsingClause|null $using
 *
 * @property      FromElement                      $left
 * @property      FromElement                      $right
 * @property-read string                           $type
 * @property      bool                             $natural
 * @property      UsingClause|Identifier[]|null    $using
 * @property      ScalarExpression|null            $on
 */
class JoinExpression extends FromElement
{
    public const CROSS = 'cross';
    public const LEFT  = 'left';
    public const RIGHT = 'right';
    public const FULL  = 'full';
    public const INNER = 'inner';

    private const ALLOWED_TYPES = [
        self::CROSS => true,
        self::LEFT  => true,
        self::RIGHT => true,
        self::FULL  => true,
        self::INNER => true
    ];

    /** @var FromElement */
    protected $p_left;
    /** @var FromElement */
    protected $p_right;
    /** @var string */
    protected $p_type;
    /** @var bool */
    protected $p_natural = false;
    /** @var UsingClause|null */
    protected $p_using = null;
    /** @var ScalarExpression|null */
    protected $p_on = null;

    public function __construct(FromElement $left, FromElement $right, string $joinType = self::INNER)
    {
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for both sides of JOIN");
        }
        if (!isset(self::ALLOWED_TYPES[$joinType])) {
            throw new InvalidArgumentException("Unknown join type '{$joinType}'");
        }

        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->p_type = $joinType;
    }

    public function setLeft(FromElement $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function setRight(FromElement $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function setNatural(bool $natural): void
    {
        if ($natural) {
            if (self::CROSS === $this->p_type) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif (!empty($this->p_using) || !empty($this->p_on)) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
        }
        $this->p_natural = $natural;
    }

    /**
     * Sets USING clause for JOIN expression
     *
     * @param null|string|iterable<Identifier>|UsingClause $using
     */
    public function setUsing($using = null): void
    {
        if (null !== $using) {
            if (self::CROSS === $this->p_type) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif (!empty($this->p_natural) || !empty($this->p_on)) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
            if (!$using instanceof UsingClause) {
                if (is_string($using)) {
                    $using = UsingClause::createFromString($this->getParserOrFail('a USING clause'), $using);
                } elseif (is_iterable($using)) {
                    $using = new UsingClause($using);
                } else {
                    throw new InvalidArgumentException(sprintf(
                        '%s requires an SQL string, an array of identifiers, or an instance of UsingClause, %s given',
                        __METHOD__,
                        is_object($using) ? 'object(' . get_class($using) . ')' : gettype($using)
                    ));
                }
            }
        }
        $this->setProperty($this->p_using, $using);
    }

    /**
     * Sets ON clause for JOIN expression
     *
     * @param null|string|ScalarExpression $on
     */
    public function setOn($on = null): void
    {
        if (null !== $on) {
            if (self::CROSS === $this->p_type) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif (!empty($this->p_natural) || !empty($this->p_using)) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
            if (is_string($on)) {
                $on = $this->getParserOrFail('an ON expression')->parseExpression($on);
            }
            if (!($on instanceof ScalarExpression)) {
                throw new InvalidArgumentException(sprintf(
                    '%s requires an SQL expression string or an instance of ScalarExpression, %s given',
                    __METHOD__,
                    is_object($on) ? 'object(' . get_class($on) . ')' : gettype($on)
                ));
            }
        }
        $this->setProperty($this->p_on, $on);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJoinExpression($this);
    }
}
