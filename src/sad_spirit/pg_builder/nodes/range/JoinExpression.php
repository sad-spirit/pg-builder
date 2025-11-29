<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
    /** @internal Maps to `$left` magic property, use the latter instead */
    protected FromElement $p_left;
    /** @internal Maps to `$right` magic property, use the latter instead */
    protected FromElement $p_right;
    /** @internal Maps to `$natural` magic property, use the latter instead */
    protected bool $p_natural = false;
    /** @internal Maps to `$using` magic property, use the latter instead */
    protected ?UsingClause $p_using = null;
    /** @internal Maps to `$on` magic property, use the latter instead */
    protected ?ScalarExpression $p_on = null;
    /** @internal Maps to `$type` magic property, use the latter instead */
    protected JoinType $p_type;

    public function __construct(FromElement $left, FromElement $right, JoinType $type = JoinType::INNER)
    {
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for both sides of JOIN");
        }

        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->p_type = $type;
    }

    /** @internal Support method for `$left` magic property, use the property instead */
    public function setLeft(FromElement $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    /** @internal Support method for `$right` magic property, use the property instead */
    public function setRight(FromElement $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    /** @internal Support method for `$natural` magic property, use the property instead */
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
     * @internal Support method for `$using` magic property, use the property instead
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
     *
     * @internal Support method for `$on` magic property, use the property instead
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
