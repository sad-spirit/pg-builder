<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
