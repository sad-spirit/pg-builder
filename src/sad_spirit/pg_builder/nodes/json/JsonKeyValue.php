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
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * Represents a key-value pair for JSON
 *
 * @property ScalarExpression   $key
 * @property JsonFormattedValue $value
 */
class JsonKeyValue extends GenericNode
{
    public function __construct(protected ScalarExpression $p_key, protected JsonFormattedValue $p_value)
    {
        $this->generatePropertyNames();
        $this->p_key->setParentNode($this);
        $this->p_value->setParentNode($this);
    }

    public function setKey(ScalarExpression $key): void
    {
        $this->setRequiredProperty($this->p_key, $key);
    }

    public function setValue(JsonFormattedValue $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonKeyValue($this);
    }
}
