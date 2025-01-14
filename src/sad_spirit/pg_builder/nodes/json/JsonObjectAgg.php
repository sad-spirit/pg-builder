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

use sad_spirit\pg_builder\{
    nodes\ScalarExpression,
    nodes\WindowDefinition,
    TreeWalker
};

/**
 * Represents json_objectagg() expression
 *
 * @property JsonKeyValue $keyValue
 */
class JsonObjectAgg extends JsonAggregate
{
    use UniqueKeysProperty;

    protected JsonKeyValue $p_keyValue;

    public function __construct(
        JsonKeyValue $keyValue,
        ?bool $absentOnNull = null,
        ?bool $uniqueKeys = null,
        ?JsonReturning $returning = null,
        ?ScalarExpression $filter = null,
        ?WindowDefinition $over = null
    ) {
        parent::__construct($absentOnNull, $returning, $filter, $over);

        $this->p_keyValue = $keyValue;
        $this->p_keyValue->setParentNode($this);

        $this->p_uniqueKeys = $uniqueKeys;
    }

    public function setKeyValue(JsonKeyValue $keyValue): void
    {
        $this->setRequiredProperty($this->p_keyValue, $keyValue);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonObjectAgg($this);
    }
}
