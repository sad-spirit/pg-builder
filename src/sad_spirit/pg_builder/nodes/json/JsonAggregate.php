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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    GenericNode,
    ScalarExpression,
    WindowDefinition
};

/**
 * Base class for nodes representing JSON aggregate functions (json_arrayagg / json_objectagg)
 *
 * @property ScalarExpression|null $filter
 * @property WindowDefinition|null $over
 */
abstract class JsonAggregate extends GenericNode implements ScalarExpression
{
    use ExpressionAtom;
    use AbsentOnNullProperty;
    use ReturningProperty;

    protected ?ScalarExpression $p_filter = null;
    protected ?WindowDefinition $p_over = null;

    public function __construct(
        ?bool $absentOnNull,
        ?JsonReturning $returning,
        ?ScalarExpression $filter,
        ?WindowDefinition $over
    ) {
        $this->generatePropertyNames();

        $this->p_absentOnNull = $absentOnNull;
        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
        if (null !== $filter) {
            $this->p_filter = $filter;
            $this->p_filter->setParentNode($this);
        }
        if (null !== $over) {
            $this->p_over = $over;
            $this->p_over->setParentNode($this);
        }
    }

    public function setFilter(?ScalarExpression $filter): void
    {
        $this->setProperty($this->p_filter, $filter);
    }

    public function setOver(?WindowDefinition $over): void
    {
        $this->setProperty($this->p_over, $over);
    }
}
