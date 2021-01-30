<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

/**
 * Interface for "array-like" AST nodes, e.g. list of tables in FROM clause
 *
 * @psalm-template TKey of array-key
 * @phpstan-template TKey
 * @template T
 * @extends \IteratorAggregate<TKey, T>
 * @extends \ArrayAccess<TKey, T>
 */
interface NodeList extends Node, \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Merges one or more lists with the current one
     *
     * The arguments can be arrays, Traversables or even strings if current class
     * implements Parseable and a Parser is available.
     *
     * @param iterable<T|string>|string ...$lists
     */
    public function merge(...$lists): void;

    /**
     * Replaces the elements of the list with the given ones
     *
     * @param iterable<T|string>|string $list strings are allowed if current class
     *                                        implements Parseable and a Parser is available
     */
    public function replace($list): void;
}
