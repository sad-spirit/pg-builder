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
 * Interface for AST nodes
 */
interface Node
{
    /**
     * Double-dispatch method supposed to call the relevant method of TreeWalker
     *
     * @param TreeWalker $walker
     * @return mixed
     */
    public function dispatch(TreeWalker $walker);

    /**
     * Adds the link to the Node containing current one
     *
     * @param Node|null $parent Node containing the current one, null if the link should
     *                          really be removed (when calling from removeChild())
     *
     * @throws exceptions\InvalidArgumentException When trying to set a child of a Node as its parent
     */
    public function setParentNode(Node $parent = null): void;

    /**
     * Returns the node containing current one
     *
     * @return Node|null
     */
    public function getParentNode(): ?Node;

    /**
     * Replaces the child Node with another one
     *
     * This is a building block for methods that change the AST, see e.g. ParameterWalker
     *
     * @param Node $oldChild
     * @param Node $newChild
     * @return Node|null $newChild in case of successful replace, null otherwise
     * @throws exceptions\InvalidArgumentException
     */
    public function replaceChild(Node $oldChild, Node $newChild): ?Node;

    /**
     * Removes the child Node (actually tries to store a null in a relevant property)
     *
     * @param Node $child
     * @return Node|null
     * @throws exceptions\InvalidArgumentException
     */
    public function removeChild(Node $child): ?Node;

    /**
     * Returns the Parser (used by some subclasses to add parts of expression in SQL string form)
     *
     * @return Parser|null
     */
    public function getParser(): ?Parser;
}
