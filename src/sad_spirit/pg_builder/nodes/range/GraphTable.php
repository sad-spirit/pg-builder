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

use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_builder\nodes\lists\LabeledExpressionList;
use sad_spirit\pg_builder\nodes\range\graph\GraphPattern;

/**
 * AST node representing graph_table() clause in FROM
 *
 * @property      QualifiedName         $name
 * @property      GraphPattern          $pattern
 * @property-read LabeledExpressionList $columns
 *
 * @since 3.4.0
 */
class GraphTable extends FromElement
{
    /** @internal Maps to `$name` magic property, use the latter instead */
    protected QualifiedName $p_name;
    /** @internal Maps to `$pattern` magic property, use the latter instead */
    protected GraphPattern $p_pattern;
    /** @internal Maps to `$columns` magic property, use the latter instead */
    protected LabeledExpressionList $p_columns;

    public function __construct(
        QualifiedName $name,
        GraphPattern $pattern,
        LabeledExpressionList $columns
    ) {
        $this->generatePropertyNames();

        $this->p_name = $name;
        $this->p_name->setParentNode($this);

        $this->p_pattern = $pattern;
        $this->p_pattern->setParentNode($this);

        $this->p_columns = $columns;
        $this->p_columns->setParentNode($this);
    }

    /** @internal Support method for `$name` magic property, use the property instead */
    public function setName(QualifiedName $name): void
    {
        $this->setRequiredProperty($this->p_name, $name);
    }

    /** @internal Support method for `$pattern` magic property, use the property instead */
    public function setPattern(GraphPattern $pattern): void
    {
        $this->setRequiredProperty($this->p_pattern, $pattern);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkGraphTable($this);
    }
}
