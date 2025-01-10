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
    protected ?StringConstant $p_path = null;

    public function __construct(Identifier $name, protected TypeName $p_type, ?StringConstant $path = null)
    {
        parent::__construct($name);
        $this->p_type->setParentNode($this);

        if (null !== $path) {
            $this->p_path = $path;
            $this->p_path->setParentNode($this);
        }
    }

    public function setType(TypeName $type): void
    {
        $this->setRequiredProperty($this->p_type, $type);
    }

    public function setPath(?StringConstant $path): void
    {
        $this->setProperty($this->p_path, $path);
    }
}
