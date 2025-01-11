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

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    ScalarExpression,
    lists\TargetList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlforest() expression (cannot be a FunctionCall due to special arguments format)
 */
class XmlForest extends TargetList implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlForest($this);
    }
}
