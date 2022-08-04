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
 * @copyright 2014-2022 Alexey Borzov
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
    JsonArgument,
    JsonArgumentList,
    JsonFormattedValue,
    JsonKeywords
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing json_table() clause in FROM
 *
 * @psalm-property JsonArgumentList              $passing
 * @psalm-property json\JsonColumnDefinitionList $columns
 *
 * @property JsonFormattedValue                                        $context
 * @property ScalarExpression                                          $path
 * @property Identifier|null                                           $pathName
 * @property JsonArgumentList|JsonArgument[]                           $passing
 * @property json\JsonColumnDefinitionList|json\JsonColumnDefinition[] $columns
 * @property json\JsonTablePlan|null                                   $plan
 * @property string|null                                               $onError
 */
class JsonTable extends LateralFromElement
{
    use HasBehaviours;

    /** @var JsonFormattedValue */
    protected $p_context;
    /** @var ScalarExpression */
    protected $p_path;
    /** @var Identifier|null */
    protected $p_pathName = null;
    /** @var JsonArgumentList */
    protected $p_passing;
    /** @var json\JsonColumnDefinitionList */
    protected $p_columns;
    /** @var json\JsonTablePlan|null */
    protected $p_plan = null;
    /** @var string|null */
    protected $p_onError;

    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?Identifier $pathName = null,
        ?JsonArgumentList $passing = null,
        ?json\JsonColumnDefinitionList $columns = null,
        ?json\JsonTablePlan $plan = null,
        ?string $onError = null
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

        if (null !== $plan) {
            $this->p_plan = $plan;
            $this->p_plan->setParentNode($this);
        }

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

    public function setPlan(?json\JsonTablePlan $plan): void
    {
        $this->setProperty($this->p_plan, $plan);
    }

    public function setOnError(?string $onError): void
    {
        /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
        $this->setBehaviour($this->p_onError, 'ON ERROR', JsonKeywords::BEHAVIOURS_TABLE, $onError);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonTable($this);
    }
}
