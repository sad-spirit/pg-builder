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

namespace sad_spirit\pg_builder\nodes\range\graph;

use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\enums\GraphElementPatternKind;
use sad_spirit\pg_builder\nodes\{
    GenericNode,
    Identifier,
    WhereOrHavingClause,
    lists\IdentifierList
};

/**
 * AST node representing either a vertex or an edge pattern
 *
 * @property      GraphElementPatternKind $kind
 * @property      ?Identifier             $variable
 * @property-read IdentifierList          $labelExpression
 * @property-read WhereOrHavingClause     $where
 */
class ElementPattern extends GenericNode implements PathPrimary
{
    protected GraphElementPatternKind $p_kind;
    protected ?Identifier $p_variable = null;
    protected IdentifierList $p_labelExpression;
    protected WhereOrHavingClause $p_where;

    public function __construct(
        GraphElementPatternKind $kind,
        ?Identifier $variable = null,
        ?IdentifierList $labelExpression = null,
        ?WhereOrHavingClause $where = null
    ) {
        $this->generatePropertyNames();

        $this->p_kind = $kind;

        $this->p_variable = $variable;
        $this->p_variable?->setParentNode($this);

        $this->p_labelExpression = $labelExpression ?? new IdentifierList();
        $this->p_labelExpression->setParentNode($this);

        $this->p_where = $where ?? new WhereOrHavingClause();
        $this->p_where->setParentNode($this);
    }

    /** @internal Support method for `$kind` magic property, use the property instead */
    public function setKind(GraphElementPatternKind $kind): void
    {
        $this->p_kind = $kind;
    }

    /** @internal Support method for `$variable` magic property, use the property instead */
    public function setVariable(?Identifier $variable): void
    {
        $this->setProperty($this->p_variable, $variable);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkElementPattern($this);
    }
}
