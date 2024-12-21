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

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Only allows non-negative integer indexes in arrays
 *
 * @template T of Node
 * @template TListInput
 * @template TInput
 * @extends GenericNodeList<int, T, TListInput, TInput>
 */
abstract class NonAssociativeList extends GenericNodeList
{
    /**
     * Forbids using non-numeric strings and negative integers for array keys
     *
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value): void
    {
        if (null !== $offset) {
            $intOffset = (int)$offset;

            if ((string)$intOffset !== (string)$offset || $intOffset < 0) {
                throw new InvalidArgumentException("Non-negative integer offsets expected, '{$offset}' given");
            }
        }

        parent::offsetSet($offset, $value);
    }

    /**
     * Converts the incoming $list to an array of Nodes dropping the keys information
     *
     * {@inheritDoc}
     */
    protected function convertToArray($list, string $method): array
    {
        $prepared = [];
        foreach ($this->prepareList($list, $method) as $value) {
            $prepared[] = $this->prepareListElement($value);
        }
        return $prepared;
    }
}
