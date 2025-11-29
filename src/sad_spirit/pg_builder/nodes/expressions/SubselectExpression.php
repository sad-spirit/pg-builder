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
    SelectCommon,
    TreeWalker,
    enums\SubselectConstruct,
    nodes\ExpressionAtom,
    nodes\GenericNode,
    nodes\ScalarExpression
};

/**
 * AST node representing a subquery appearing in scalar expressions, possibly with a subquery operator applied
 *
 * @property      SelectCommon            $query
 * @property-read SubselectConstruct|null $operator
 */
class SubselectExpression extends GenericNode implements ScalarExpression
{
    use ExpressionAtom;

    /** @internal Maps to `$query` magic property, use the latter instead */
    protected SelectCommon $p_query;
    /** @internal Maps to `$operator` magic property, use the latter instead */
    protected ?SubselectConstruct $p_operator;

    public function __construct(SelectCommon $query, ?SubselectConstruct $operator = null)
    {
        $this->generatePropertyNames();

        $this->p_query = $query;
        $this->p_query->setParentNode($this);

        $this->p_operator = $operator;
    }

    /** @internal Support method for `$query` magic property, use the property instead */
    public function setQuery(SelectCommon $query): void
    {
        $this->setRequiredProperty($this->p_query, $query);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkSubselectExpression($this);
    }
}
