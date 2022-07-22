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
    ScalarExpression,
    TypeName
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json() expression
 *
 * @property JsonFormattedValue $expression
 */
class JsonConstructor extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use UniqueKeysProperty;
    use ReturningTypenameProperty;

    /** @var JsonFormattedValue */
    protected $p_expression;

    public function __construct(JsonFormattedValue $expression, ?bool $uniqueKeys = null, ?TypeName $returning = null)
    {
        $this->generatePropertyNames();

        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);

        $this->p_uniqueKeys = $uniqueKeys;

        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
    }

    public function setExpression(JsonFormattedValue $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonConstructor($this);
    }
}
