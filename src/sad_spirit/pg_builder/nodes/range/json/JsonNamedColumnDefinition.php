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

namespace sad_spirit\pg_builder\nodes\range\json;

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\Identifier;

/**
 * Base class for column definitions in json_table() clause that have name specified
 *
 * @property Identifier $name
 */
abstract class JsonNamedColumnDefinition extends GenericNode implements JsonColumnDefinition
{
    /** @internal Maps to `$name` magic property, use the latter instead */
    protected Identifier $p_name;

    public function __construct(Identifier $name)
    {
        $this->generatePropertyNames();

        $this->p_name = $name;
        $this->p_name->setParentNode($this);
    }

    /** @internal Support method for `$name` magic property, use the property instead */
    public function setName(Identifier $name): void
    {
        $this->setRequiredProperty($this->p_name, $name);
    }
}
