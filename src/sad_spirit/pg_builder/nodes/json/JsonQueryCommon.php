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
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

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

    public const BEHAVIOUR_TRUE         = 'true';
    public const BEHAVIOUR_ERROR        = 'error';
    public const BEHAVIOUR_UNKNOWN      = 'unknown';
    public const BEHAVIOUR_FALSE        = 'false';
    public const BEHAVIOUR_NULL         = 'null';
    public const BEHAVIOUR_EMPTY_ARRAY  = 'empty array';
    public const BEHAVIOUR_EMPTY_OBJECT = 'empty object';

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

    /**
     * Sets the value for "ON EMPTY" / "ON ERROR" behaviour clause
     *
     * @param string|ScalarExpression|null $property
     * @param string                       $clauseName Name of the clause (for exception messages only)
     * @param array                        $allowed
     * @param string|ScalarExpression|null $value
     * @return void
     */
    final protected function setBehaviour(&$property, string $clauseName, array $allowed, $value): void
    {
        if (null !== $value) {
            if (!is_string($value) && !($value instanceof ScalarExpression)) {
                throw new InvalidArgumentException(sprintf(
                    "Either a string or an instance of ScalarExpression expected for %s clause, %s given",
                    $clauseName,
                    is_object($value) ? 'object(' . get_class($value) . ')' : gettype($value)
                ));
            } elseif (is_string($value) && !in_array($value, $allowed)) {
                throw new InvalidArgumentException(sprintf(
                    "Unrecognized value '%s' for %s clause, expected one of '%s'",
                    $value,
                    $clauseName,
                    implode("', '", $allowed)
                ));
            }
        }

        if (!is_string($property) && !is_string($value)) {
            $this->setProperty($property, $value);
            return;
        }
        if ($property instanceof ScalarExpression) {
            $property->setParentNode(null);
        }
        if ($value instanceof ScalarExpression) {
            $value->setParentNode($this);
        }
        $property = $value;
    }
}
