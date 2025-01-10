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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\Keyword;
use sad_spirit\pg_builder\nodes\lists\OrderByList;
use sad_spirit\pg_builder\nodes\lists\FunctionArgumentList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a function call (func_application or func_expr_common_subexpr)
 *
 * This will be wrapped by either expressions\FunctionExpression for scalar
 * contexts where window functions are possible, or by range\FunctionCall
 * for functions in FROM
 *
 * @property-read QualifiedName             $name
 * @property      FunctionArgumentList|Star $arguments
 * @property-read bool                      $distinct
 * @property-read bool                      $variadic
 * @property      OrderByList               $order
 */
class FunctionCall extends GenericNode implements FunctionLike
{
    protected QualifiedName $p_name;
    protected FunctionArgumentList|Star $p_arguments;
    protected OrderByList $p_order;

    public function __construct(
        string|QualifiedName $funcName,
        FunctionArgumentList|Star|null $arguments = null,
        protected bool $p_distinct = false,
        protected bool $p_variadic = false,
        ?OrderByList $orderBy = null
    ) {
        if (is_string($funcName)) {
            // If we just create QualifiedName from $funcName the result will not be what user expects:
            // the keyword will appear double-quoted in output
            if (null !== Keyword::tryFrom($funcName)) {
                throw new InvalidArgumentException(
                    self::class . " no longer accepts SQL keywords for function names ('{$funcName}' given)"
                );
            }
            $funcName = new QualifiedName($funcName);
        }

        $this->generatePropertyNames();

        $this->p_name = $funcName;
        $this->p_name->setParentNode($this);

        $this->p_arguments = $arguments ?? new FunctionArgumentList();
        $this->p_arguments->setParentNode($this);

        $this->p_order = $orderBy ?? new OrderByList();
        $this->p_order->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkFunctionCall($this);
    }
}
