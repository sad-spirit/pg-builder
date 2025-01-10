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
    Identifier,
    TypeName,
    expressions\StringConstant,
    json\HasBehaviours
};
use sad_spirit\pg_builder\enums\JsonBehaviour;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for EXISTS column definitions in json_table() clause
 *
 * @property JsonBehaviour|null $onError
 */
class JsonExistsColumnDefinition extends JsonTypedColumnDefinition
{
    use HasBehaviours;

    protected ?JsonBehaviour $p_onError = null;

    public function __construct(
        Identifier $name,
        TypeName $type,
        ?StringConstant $path = null,
        ?JsonBehaviour $onError = null
    ) {
        parent::__construct($name, $type, $path);
        $this->setOnError($onError);
    }

    public function setOnError(?JsonBehaviour $onError): void
    {
        /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
        $this->setBehaviour($this->p_onError, false, $onError);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonExistsColumnDefinition($this);
    }
}
