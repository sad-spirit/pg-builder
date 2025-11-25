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
use sad_spirit\pg_builder\enums\NormalizeForm;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing NORMALIZE(...) function call with special arguments format
 *
 * Previously this was parsed to a `FunctionExpression` node having `pg_catalog.normalize` as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property      ScalarExpression $argument
 * @property-read ?NormalizeForm   $form
 */
class NormalizeExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected ScalarExpression $p_argument;
    protected ?NormalizeForm $p_form;

    public function __construct(ScalarExpression $argument, ?NormalizeForm $form = null)
    {
        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_form = $form;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkNormalizeExpression($this);
    }
}
