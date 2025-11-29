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

use sad_spirit\pg_builder\nodes\FunctionLike;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a function call in FROM clause
 *
 * @property-read FunctionLike $function
 */
class FunctionCall extends FunctionFromElement
{
    /** @internal Maps to `$function` magic property, use the latter instead */
    protected FunctionLike $p_function;

    public function __construct(FunctionLike $function)
    {
        $this->generatePropertyNames();

        $this->p_function = $function;
        $this->p_function->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkRangeFunctionCall($this);
    }
}
