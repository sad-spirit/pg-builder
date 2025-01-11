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
 * Trait for Nodes that cannot legitimately be parents for the Nodes of the same type
 *
 * Implements setParentNode() method without redundant checks for circular reference
 *
 * @require-extends GenericNode
 */
trait NonRecursiveNode
{
    /**
     * Link to the Node containing current one
     * @var Node|null
     */
    protected ?Node $parentNode = null;

    /**
     * Flag for preventing endless recursion in setParentNode()
     * @var bool
     */
    protected bool $settingParentNode = false;

    /**
     * Checks in base setParentNode() are redundant as this is a leaf node
     */
    public function setParentNode(?Node $parent): void
    {
        // no-op? recursion?
        if ($parent === $this->parentNode || $this->settingParentNode) {
            return;
        }

        $this->settingParentNode = true;
        try {
            $this->parentNode?->removeChild($this);
            $this->parentNode = $parent;

        } finally {
            $this->settingParentNode = false;
        }
    }
}
