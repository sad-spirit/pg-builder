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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node;

/**
 * Trait for Nodes that cannot legitimately be parents for the Nodes of the same type
 *
 * Implements setParentNode() method without redundant checks for circular reference
 */
trait NonRecursiveNode
{
    /**
     * Link to the Node containing current one
     * @var Node|null
     */
    protected $parentNode = null;

    /**
     * Flag for preventing endless recursion in setParentNode()
     * @var bool
     */
    protected $settingParentNode = false;

    /**
     * Checks in base setParentNode() are redundant as this is a leaf node
     *
     * @param Node|null $parent
     */
    public function setParentNode(Node $parent = null): void
    {
        // no-op? recursion?
        if ($parent === $this->parentNode || $this->settingParentNode) {
            return;
        }

        $this->settingParentNode = true;
        try {
            if (null !== $this->parentNode) {
                $this->parentNode->removeChild($this);
            }
            $this->parentNode = $parent;

        } finally {
            $this->settingParentNode = false;
        }
    }
}
