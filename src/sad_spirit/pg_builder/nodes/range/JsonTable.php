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

    protected ?Identifier $p_pathName = null;
    protected JsonArgumentList $p_passing;
    protected json\JsonColumnDefinitionList $p_columns;
    protected ?JsonBehaviour $p_onError = null;

    public function __construct(
        protected JsonFormattedValue $p_context,
        protected ScalarExpression $p_path,
        ?Identifier $pathName = null,
        ?JsonArgumentList $passing = null,
        ?json\JsonColumnDefinitionList $columns = null,
        ?JsonBehaviour $onError = null
    ) {
        $this->generatePropertyNames();
        $this->p_context->setParentNode($this);
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

    public function setContext(JsonFormattedValue $context): void
    {
        $this->setRequiredProperty($this->p_context, $context);
    }

    public function setPath(ScalarExpression $path): void
    {
        $this->setRequiredProperty($this->p_path, $path);
    }

    public function setPathName(?Identifier $pathName): void
    {
        $this->setProperty($this->p_pathName, $pathName);
    }

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
