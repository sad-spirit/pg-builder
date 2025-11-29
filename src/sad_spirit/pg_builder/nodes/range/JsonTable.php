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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\nodes\{
    Identifier,
    ScalarExpression
};
use sad_spirit\pg_builder\nodes\json\{
    HasBehaviours,
    JsonArgumentList,
    JsonFormattedValue
};
use sad_spirit\pg_builder\enums\JsonBehaviour;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing json_table() clause in FROM
 *
 * @property JsonFormattedValue            $context
 * @property ScalarExpression              $path
 * @property Identifier|null               $pathName
 * @property JsonArgumentList              $passing
 * @property json\JsonColumnDefinitionList $columns
 * @property JsonBehaviour|null            $onError
 */
class JsonTable extends LateralFromElement
{
    use HasBehaviours;

    /** @internal Maps to `$context` magic property, use the latter instead */
    protected JsonFormattedValue $p_context;
    /** @internal Maps to `$path` magic property, use the latter instead */
    protected ScalarExpression $p_path;
    /** @internal Maps to `$pathName` magic property, use the latter instead */
    protected ?Identifier $p_pathName = null;
    /** @internal Maps to `$passing` magic property, use the latter instead */
    protected JsonArgumentList $p_passing;
    /** @internal Maps to `$columns` magic property, use the latter instead */
    protected json\JsonColumnDefinitionList $p_columns;
    /** @internal Maps to `$onError` magic property, use the latter instead */
    protected ?JsonBehaviour $p_onError = null;

    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?Identifier $pathName = null,
        ?JsonArgumentList $passing = null,
        ?json\JsonColumnDefinitionList $columns = null,
        ?JsonBehaviour $onError = null
    ) {
        $this->generatePropertyNames();

        $this->p_context = $context;
        $this->p_context->setParentNode($this);

        $this->p_path = $path;
        $this->p_path->setParentNode($this);

        if (null !== $pathName) {
            $this->p_pathName = $pathName;
            $this->p_pathName->setParentNode($this);
        }

        $this->p_passing = $passing ?? new JsonArgumentList();
        $this->p_passing->setParentNode($this);

        $this->p_columns = $columns ?? new json\JsonColumnDefinitionList([]);
        $this->p_columns->setParentNode($this);

        $this->setOnError($onError);
    }

    /** @internal Support method for `$context` magic property, use the property instead */
    public function setContext(JsonFormattedValue $context): void
    {
        $this->setRequiredProperty($this->p_context, $context);
    }

    /** @internal Support method for `$path` magic property, use the property instead */
    public function setPath(ScalarExpression $path): void
    {
        $this->setRequiredProperty($this->p_path, $path);
    }

    /** @internal Support method for `$pathName` magic property, use the property instead */
    public function setPathName(?Identifier $pathName): void
    {
        $this->setProperty($this->p_pathName, $pathName);
    }

    /** @internal Support method for `$onError` magic property, use the property instead */
    public function setOnError(?JsonBehaviour $onError): void
    {
        /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
        $this->setBehaviour($this->p_onError, false, $onError);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonTable($this);
    }
}
