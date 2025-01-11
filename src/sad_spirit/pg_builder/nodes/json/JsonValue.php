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
 * AST node representing the json_value() expression
 *
 * @property JsonBehaviour|ScalarExpression|null $onEmpty
 * @property JsonBehaviour|ScalarExpression|null $onError
 */
class JsonValue extends JsonQueryCommon
{
    use ReturningProperty;
    use HasBehaviours;

    protected JsonBehaviour|ScalarExpression|null $p_onEmpty = null;
    protected JsonBehaviour|ScalarExpression|null $p_onError = null;

    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?JsonArgumentList $passing = null,
        ?JsonReturning $returning = null,
        JsonBehaviour|ScalarExpression|null $onEmpty = null,
        JsonBehaviour|ScalarExpression|null $onError = null
    ) {
        parent::__construct($context, $path, $passing);
        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
        $this->setOnEmpty($onEmpty);
        $this->setOnError($onError);
    }

    /**
     * Sets the value for `ON EMPTY` clause (`DEFAULT ...` is represented by an implementation of `ScalarExpression`)
     */
    public function setOnEmpty(JsonBehaviour|ScalarExpression|null $onEmpty): void
    {
        $this->setBehaviour($this->p_onEmpty, true, $onEmpty);
    }

    /**
     * Sets the value for `ON ERROR` clause (`DEFAULT ...` is represented by an implementation of `ScalarExpression`)
     */
    public function setOnError(JsonBehaviour|ScalarExpression|null $onError): void
    {
        $this->setBehaviour($this->p_onError, false, $onError);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonValue($this);
    }
}
