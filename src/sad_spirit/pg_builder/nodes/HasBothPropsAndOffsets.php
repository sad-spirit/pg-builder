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

namespace sad_spirit\pg_builder\nodes;

/**
 * Trait for descendants of GenericNodeList that expose properties alongside array offsets
 *
 * Serializes / unserializes both array instead of just $offsets
 */
trait HasBothPropsAndOffsets
{
    public function serialize(): string
    {
        return serialize([$this->collectProperties(), $this->offsets]);
    }

    public function unserialize($serialized)
    {
        [$props, $this->offsets] = unserialize($serialized);
        $this->unserializeProperties($props);
        $this->updateParentNodeOnOffsets();
    }

    abstract protected function collectProperties(): array;

    abstract protected function unserializeProperties(array $properties): void;

    abstract protected function updateParentNodeOnOffsets(): void;
}
