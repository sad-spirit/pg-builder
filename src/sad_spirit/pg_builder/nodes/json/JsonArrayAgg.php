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
    lists\OrderByList,
    ScalarExpression,
    WindowDefinition
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents json_objectagg() expression
 *
 * @property JsonFormattedValue $value
 * @property OrderByList|null   $order
 */
class JsonArrayAgg extends JsonAggregate
{
    protected ?OrderByList $p_order = null;

    public function __construct(
        protected JsonFormattedValue $p_value,
        ?OrderByList $order = null,
        ?bool $absentOnNull = null,
        ?JsonReturning $returning = null,
        ?ScalarExpression $filter = null,
        ?WindowDefinition $over = null
    ) {
        parent::__construct($absentOnNull, $returning, $filter, $over);
        $this->p_value->setParentNode($this);

        if (null !== $order) {
            $this->p_order = $order;
            $this->p_order->setParentNode($this);
        }
    }

    public function setValue(JsonFormattedValue $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function setOrder(?OrderByList $order): void
    {
        $this->setProperty($this->p_order, $order);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonArrayAgg($this);
    }
}
