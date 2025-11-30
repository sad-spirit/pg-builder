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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node;

/**
 * Trait for Nodes that cannot legitimately be ancestors for the Nodes of the same type
 *
 * Implements `setParentNode()` method without redundant checks for circular reference
 *
 * @require-extends GenericNode
 */
trait NonRecursiveNode
{
    /**
     * Link to the Node containing current one
     * @var \WeakReference<Node>|null
     * @internal
     */
    protected ?\WeakReference $parentNode = null;

    /**
     * Flag for preventing endless recursion in setParentNode()
     * @var bool
     * @internal
     */
    protected bool $settingParentNode = false;

    /**
     * Adds the link to the Node containing current one
     *
     * Checks in base {@see \sad_spirit\pg_builder\nodes\GenericNode::setParentNode() setParentNode()}
     * are redundant as this `Node` cannot have instances of `self` as descendants
     *
     * @param Node|null $parent Node containing the current one, `null` if the link should
     *                          really be removed (e.g. when calling from `removeChild()`)
     */
    public function setParentNode(?Node $parent): void
    {
        // no-op? recursion?
        if ($parent === $this->parentNode?->get() || $this->settingParentNode) {
            return;
        }

        $this->settingParentNode = true;
        try {
            $this->parentNode?->get()?->removeChild($this);
            $this->parentNode = $parent ? \WeakReference::create($parent) : null;

        } finally {
            $this->settingParentNode = false;
        }
    }
}
