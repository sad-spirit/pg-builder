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

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\Identifier;

/**
 * Base class for column definitions in XMLTABLE clause
 *
 * @property Identifier $name
 */
abstract class XmlColumnDefinition extends GenericNode
{
    protected Identifier $p_name;

    public function __construct(Identifier $name)
    {
        $this->generatePropertyNames();

        $this->p_name = $name;
        $this->p_name->setParentNode($this);
    }

    public function setName(Identifier $name): void
    {
        $this->setRequiredProperty($this->p_name, $name);
    }
}
