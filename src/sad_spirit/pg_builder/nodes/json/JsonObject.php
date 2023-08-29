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
 * @copyright 2014-2023 Alexey Borzov
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
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_object() expression
 *
 * @psalm-property JsonKeyValueList $arguments
 *
 * @property JsonKeyValueList|JsonKeyValue[] $arguments
 */
class JsonObject extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use AbsentOnNullProperty;
    use ReturningProperty;
    use UniqueKeysProperty;

    /** @var JsonKeyValueList */
    protected $p_arguments;

    public function __construct(
        ?JsonKeyValueList $arguments = null,
        ?bool $absentOnNull = null,
        ?bool $uniqueKeys = null,
        ?JsonReturning $returning = null
    ) {
        $this->generatePropertyNames();

        $this->p_arguments = $arguments ?? new JsonKeyValueList();
        $this->p_arguments->setParentNode($this);

        $this->p_absentOnNull = $absentOnNull;
        $this->p_uniqueKeys = $uniqueKeys;

        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonObject($this);
    }
}
