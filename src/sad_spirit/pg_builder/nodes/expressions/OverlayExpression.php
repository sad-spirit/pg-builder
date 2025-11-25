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
 * AST node representing OVERLAY(...) function call with special arguments format
 *
 * Previously this was parsed to a `FunctionExpression` node having `pg_catalog.overlay` as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property ScalarExpression      $string
 * @property ScalarExpression      $newSubstring
 * @property ScalarExpression      $start
 * @property ScalarExpression|null $count
 */
class OverlayExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected ScalarExpression $p_string;
    protected ScalarExpression $p_newSubstring;
    protected ScalarExpression $p_start;
    protected ?ScalarExpression $p_count;

    public function __construct(
        ScalarExpression $string,
        ScalarExpression $newSubstring,
        ScalarExpression $start,
        ?ScalarExpression $count = null
    ) {
        $this->generatePropertyNames();

        $check = [];
        foreach ([$string, $newSubstring, $start, $count] as $object) {
            if (null === $object) {
                continue;
            }
            if (isset($check[$id = \spl_object_id($object)])) {
                throw new InvalidArgumentException("Cannot use the same Node for different OVERLAY arguments");
            }
            $check[$id] = true;
        }

        $this->p_string = $string;
        $this->p_string->setParentNode($this);

        $this->p_newSubstring = $newSubstring;
        $this->p_newSubstring->setParentNode($this);

        $this->p_start = $start;
        $this->p_start->setParentNode($this);

        if (null !== $count) {
            $this->p_count = $count;
            $this->p_count->setParentNode($this);
        }
    }

    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    public function setNewSubstring(ScalarExpression $newSubstring): void
    {
        $this->setRequiredProperty($this->p_newSubstring, $newSubstring);
    }

    public function setStart(ScalarExpression $start): void
    {
        $this->setRequiredProperty($this->p_start, $start);
    }

    public function setCount(?ScalarExpression $count): void
    {
        $this->setProperty($this->p_count, $count);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkOverlayExpression($this);
    }
}
