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

use sad_spirit\pg_builder\enums\JsonBehaviour;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_exists() expression
 *
 * @property JsonBehaviour|null $onError
 */
class JsonExists extends JsonQueryCommon
{
    use HasBehaviours;

    protected ?JsonBehaviour $p_onError = null;

    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?JsonArgumentList $passing = null,
        ?JsonBehaviour $onError = null
    ) {
        parent::__construct($context, $path, $passing);

        $this->setOnError($onError);
    }

    /**
     * Sets the value for `ON ERROR` clause
     */
    public function setOnError(?JsonBehaviour $onError): void
    {
        /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
        $this->setBehaviour($this->p_onError, false, $onError);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonExists($this);
    }
}
