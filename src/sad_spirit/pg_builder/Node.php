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
 * Interface for AST nodes
 */
interface Node
{
    /**
     * Double-dispatch method supposed to call the relevant method of TreeWalker
     */
    public function dispatch(TreeWalker $walker): mixed;

    /**
     * Adds the link to the Node containing current one
     *
     * @param Node|null $parent Node containing the current one, null if the link should
     *                          really be removed (when calling from removeChild())
     *
     * @throws exceptions\InvalidArgumentException When trying to set a child of a Node as its parent
     */
    public function setParentNode(?Node $parent): void;

    /**
     * Returns the node containing current one
     */
    public function getParentNode(): ?Node;

    /**
     * Replaces the child Node with another one
     *
     * This is a building block for methods that change the AST, see e.g. ParameterWalker
     *
     * @return Node|null $newChild in case of successful replace, null otherwise
     * @throws exceptions\InvalidArgumentException
     */
    public function replaceChild(Node $oldChild, Node $newChild): ?Node;

    /**
     * Removes the child Node (actually tries to store a null in a relevant property)
     *
     * @throws exceptions\InvalidArgumentException
     */
    public function removeChild(Node $child): ?Node;

    /**
     * Returns the Parser (used by some subclasses to add parts of expression in SQL string form)
     */
    public function getParser(): ?Parser;
}
