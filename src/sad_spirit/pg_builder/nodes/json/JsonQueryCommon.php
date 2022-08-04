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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};

/**
 * Base class for JSON query expressions
 *
 * Roughly corresponds to json_api_common_syntax production without json_as_path_name_clause_opt, as the latter
 * is only used by json_table() and causes an error when actually appearing in JSON query functions
 *
 * @psalm-property JsonArgumentList $passing
 *
 * @property JsonFormattedValue              $context
 * @property ScalarExpression                $path
 * @property JsonArgumentList|JsonArgument[] $passing
 */
abstract class JsonQueryCommon extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @var JsonFormattedValue */
    protected $p_context;
    /** @var ScalarExpression */
    protected $p_path;
    /** @var JsonArgumentList */
    protected $p_passing;

    public function __construct(
        JsonFormattedValue $context,
        ScalarExpression $path,
        ?JsonArgumentList $passing = null
    ) {
        $this->generatePropertyNames();

        $this->p_context = $context;
        $this->p_context->setParentNode($this);

        $this->p_path = $path;
        $this->p_path->setParentNode($this);

        $this->p_passing = $passing ?? new JsonArgumentList();
        $this->p_passing->setParentNode($this);
    }

    public function setContext(JsonFormattedValue $context): void
    {
        $this->setRequiredProperty($this->p_context, $context);
    }

    public function setPath(ScalarExpression $path): void
    {
        $this->setRequiredProperty($this->p_path, $path);
    }
}
