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

namespace sad_spirit\pg_builder\nodes\lists;

use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * List of function arguments, unlike most other lists can contain associative keys
 *
 * @extends GenericNodeList<int|string, ScalarExpression, iterable<ScalarExpression>, ScalarExpression>
 */
class FunctionArgumentList extends GenericNodeList
{
    protected static function getAllowedElementClasses(): array
    {
        return [ScalarExpression::class];
    }

    /**
     * Converts the incoming $list to an array of Nodes keeping the keys information
     *
     * {@inheritDoc}
     */
    protected function convertToArray($list, string $method): array
    {
        $prepared = [];
        foreach ($this->prepareList($list, $method) as $k => $v) {
            $prepared[$k] = $this->prepareListElement($v);
        }
        return $prepared;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkFunctionArgumentList($this);
    }
}
