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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\{
    enums\JoinType,
    exceptions\InvalidArgumentException,
    nodes\Identifier,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node for JOIN expression in FROM clause
 *
 * @property      FromElement           $left
 * @property      FromElement           $right
 * @property-read JoinType              $type
 * @property      bool                  $natural
 * @property      UsingClause|null      $using
 * @property      ScalarExpression|null $on
 */
class JoinExpression extends FromElement
{
    protected FromElement $p_left;
    protected FromElement $p_right;
    protected bool $p_natural = false;
    protected ?UsingClause $p_using = null;
    protected ?ScalarExpression $p_on = null;

    public function __construct(FromElement $left, FromElement $right, protected JoinType $p_type = JoinType::INNER)
    {
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for both sides of JOIN");
        }

        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);
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
            if (JoinType::CROSS === $this->p_type) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif (null !== $this->p_using || null !== $this->p_on) {
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
    public function setUsing(null|string|iterable|UsingClause $using): void
    {
        if (null !== $using) {
            if (JoinType::CROSS === $this->p_type) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif ($this->p_natural || null !== $this->p_on) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
            if (!$using instanceof UsingClause) {
                $using = \is_string($using)
                    ? UsingClause::createFromString($this->getParserOrFail('a USING clause'), $using)
                    : new UsingClause($using);
            }
        }
        $this->setProperty($this->p_using, $using);
    }

    /**
     * Sets ON clause for JOIN expression
     */
    public function setOn(null|string|ScalarExpression $on): void
    {
        if (null !== $on) {
            if (JoinType::CROSS === $this->p_type) {
                throw new InvalidArgumentException('No join conditions are allowed for CROSS JOIN');
            } elseif ($this->p_natural || null !== $this->p_using) {
                throw new InvalidArgumentException('Only one of NATURAL, USING, ON clauses should be set for JOIN');
            }
            if (\is_string($on)) {
                $on = $this->getParserOrFail('an ON expression')->parseExpression($on);
            }
        }
        $this->setProperty($this->p_on, $on);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJoinExpression($this);
    }
}
