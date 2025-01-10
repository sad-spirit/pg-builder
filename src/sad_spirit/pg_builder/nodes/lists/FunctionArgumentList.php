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
