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

use sad_spirit\pg_builder\{
    enums\IsJsonType,
    enums\ScalarExpressionAssociativity,
    enums\ScalarExpressionPrecedence,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing "foo ... IS [NOT] JSON ..." expression with all bells and whistles
 *
 * Cannot be represented by IsExpression due to aforementioned bells and whistles
 *
 * @property ScalarExpression $argument
 * @property ?IsJsonType      $type
 * @property ?bool            $uniqueKeys
 */
class IsJsonExpression extends NegatableExpression
{
    protected ScalarExpression $p_argument;
    protected ?IsJsonType $p_type;
    protected ?bool $p_uniqueKeys;

    public function __construct(
        ScalarExpression $argument,
        bool $not = false,
        ?IsJsonType $type = null,
        ?bool $uniqueKeys = null
    ) {
        $this->generatePropertyNames();

        $this->setType($type);

        $this->p_argument   = $argument;
        $this->p_argument->setParentNode($this);
        $this->p_not        = $not;
        $this->p_uniqueKeys = $uniqueKeys;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setType(?IsJsonType $type): void
    {
        $this->p_type = $type;
    }

    public function setUniqueKeys(?bool $unique): void
    {
        $this->p_uniqueKeys = $unique;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkIsJsonExpression($this);
    }

    public function getPrecedence(): ScalarExpressionPrecedence
    {
        return ScalarExpressionPrecedence::IS;
    }

    public function getAssociativity(): ScalarExpressionAssociativity
    {
        return ScalarExpressionAssociativity::NONE;
    }
}
