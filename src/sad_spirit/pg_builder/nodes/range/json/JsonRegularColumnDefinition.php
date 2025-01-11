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
    ScalarExpression,
    TypeName
};
use sad_spirit\pg_builder\enums\JsonBehaviour;
use sad_spirit\pg_builder\enums\JsonWrapper;
use sad_spirit\pg_builder\nodes\expressions\StringConstant;
use sad_spirit\pg_builder\nodes\json\{
    HasBehaviours,
    JsonFormat,
    WrapperAndQuotesProperties
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for regular column definitions in json_table() expression
 *
 * @property-read JsonFormat|null                     $format
 * @property      JsonBehaviour|ScalarExpression|null $onEmpty
 * @property      JsonBehaviour|ScalarExpression|null $onError
 */
class JsonRegularColumnDefinition extends JsonTypedColumnDefinition
{
    use WrapperAndQuotesProperties;
    use HasBehaviours;

    protected ?JsonFormat $p_format = null;
    protected JsonBehaviour|ScalarExpression|null $p_onEmpty = null;
    protected JsonBehaviour|ScalarExpression|null $p_onError = null;

    public function __construct(
        Identifier $name,
        TypeName $type,
        ?JsonFormat $format = null,
        ?StringConstant $path = null,
        ?JsonWrapper $wrapper = null,
        ?bool $keepQuotes = null,
        JsonBehaviour|ScalarExpression|null $onEmpty = null,
        JsonBehaviour|ScalarExpression|null $onError = null
    ) {
        parent::__construct($name, $type, $path);
        $this->setWrapper($wrapper);
        $this->setKeepQuotes($keepQuotes);
        $this->setOnEmpty($onEmpty);
        $this->setOnError($onError);

        if (null !== $format) {
            $this->p_format = $format;
            $this->p_format->setParentNode($this);
        }
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
        return $walker->walkJsonRegularColumnDefinition($this);
    }
}
