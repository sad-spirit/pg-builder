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

namespace sad_spirit\pg_builder;

/**
 * Interface for "array-like" AST nodes, e.g. list of tables in FROM clause
 *
 * @template TKey of array-key
 * @template T
 * @template TListInput
 * @extends \IteratorAggregate<TKey, T>
 * @extends \ArrayAccess<TKey, T>
 */
interface NodeList extends Node, \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Merges one or more lists with the current one
     *
     * The arguments can be traversables or even strings if current class
     * implements `Parseable` and a `Parser` is available.
     *
     * @param TListInput ...$lists
     */
    public function merge(...$lists): void;

    /**
     * Replaces the elements of the list with the given ones
     *
     * @param TListInput $list strings are allowed if current class
     *                         implements `Parseable` and a `Parser` is available
     */
    public function replace($list): void;
}
