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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing SUBSTRING(string FROM ...) function call with special arguments format
 *
 * Previously this was parsed to a `FunctionExpression` node having `pg_catalog.substring` as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate `Node` with SQL standard output.
 *
 * @property      ScalarExpression      $string
 * @property      ScalarExpression|null $from
 * @property      ScalarExpression|null $for
 */
class SubstringFromExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @internal Maps to `$string` magic property, use the latter instead */
    protected ScalarExpression $p_string;
    /** @internal Maps to `$from` magic property, use the latter instead */
    protected ?ScalarExpression $p_from;
    /** @internal Maps to `$for` magic property, use the latter instead */
    protected ?ScalarExpression $p_for;

    public function __construct(
        ScalarExpression $string,
        ?ScalarExpression $from = null,
        ?ScalarExpression $for = null
    ) {
        if (null === $from && null === $for) {
            throw new InvalidArgumentException("At least one of FROM and FOR arguments is required");
        }
        if ($string === $from || $from === $for || $string === $for) {
            throw new InvalidArgumentException("Cannot use the same Node for different arguments of SUBSTRING");
        }

        $this->generatePropertyNames();

        $this->p_string = $string;
        $this->p_string->setParentNode($this);

        if (null !== $from) {
            $this->p_from = $from;
            $this->p_from->setParentNode($this);
        }

        if (null !== $for) {
            $this->p_for = $for;
            $this->p_for->setParentNode($this);
        }
    }

    /** @internal Support method for `$string` magic property, use the property instead */
    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    /** @internal Support method for `$from` magic property, use the property instead */
    public function setFrom(?ScalarExpression $from): void
    {
        if (null === $from && null === $this->p_for) {
            throw new InvalidArgumentException("At least one of FROM and FOR arguments is required");
        }
        $this->setProperty($this->p_from, $from);
    }

    /** @internal Support method for `$for` magic property, use the property instead */
    public function setFor(?ScalarExpression $for): void
    {
        if (null === $for && null === $this->p_from) {
            throw new InvalidArgumentException("At least one of FROM and FOR arguments is required");
        }
        $this->setProperty($this->p_for, $for);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSubstringFromExpression($this);
    }
}
