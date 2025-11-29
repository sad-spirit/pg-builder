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

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an element of PASSING clause of various JSON expressions
 *
 * @property JsonFormattedValue $value
 * @property Identifier         $alias
 */
class JsonArgument extends GenericNode
{
    /** @internal Maps to `$value` magic property, use the latter instead */
    protected JsonFormattedValue $p_value;
    /** @internal Maps to `$alias` magic property, use the latter instead */
    protected Identifier $p_alias;

    public function __construct(JsonFormattedValue $value, Identifier $alias)
    {
        $this->generatePropertyNames();

        $this->p_value = $value;
        $this->p_value->setParentNode($this);

        $this->p_alias = $alias;
        $this->p_alias->setParentNode($this);
    }

    /** @internal Support method for `$value` magic property, use the property instead */
    public function setValue(JsonFormattedValue $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    /** @internal Support method for `$alias` magic property, use the property instead */
    public function setAlias(Identifier $alias): void
    {
        $this->setRequiredProperty($this->p_alias, $alias);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonArgument($this);
    }
}
