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

use sad_spirit\pg_builder\nodes\{
    expressions\StringConstant,
    Identifier,
    TypeName
};

/**
 * Base class for column definitions in json_table() clause that have type specified
 *
 * @property TypeName            $type
 * @property StringConstant|null $path
 */
abstract class JsonTypedColumnDefinition extends JsonNamedColumnDefinition
{
    /** @internal Maps to `$path` magic property, use the latter instead */
    protected ?StringConstant $p_path = null;
    /** @internal Maps to `$type` magic property, use the latter instead */
    protected TypeName $p_type;

    public function __construct(Identifier $name, TypeName $type, ?StringConstant $path = null)
    {
        parent::__construct($name);

        $this->p_type = $type;
        $this->p_type->setParentNode($this);

        if (null !== $path) {
            $this->p_path = $path;
            $this->p_path->setParentNode($this);
        }
    }

    /** @internal Support method for `$type` magic property, use the property instead */
    public function setType(TypeName $type): void
    {
        $this->setRequiredProperty($this->p_type, $type);
    }

    /** @internal Support method for `$path` magic property, use the property instead */
    public function setPath(?StringConstant $path): void
    {
        $this->setProperty($this->p_path, $path);
    }
}
