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
