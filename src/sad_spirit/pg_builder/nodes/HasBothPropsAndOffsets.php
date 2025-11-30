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

/**
 * Trait for descendants of GenericNodeList that expose properties alongside array offsets
 *
 * Serializes / unserializes both arrays instead of just `$offsets`
 *
 * @psalm-require-extends lists\GenericNodeList
 */
trait HasBothPropsAndOffsets
{
    public function __serialize(): array
    {
        return [$this->collectProperties(), $this->offsets];
    }

    public function __unserialize(array $data): void
    {
        [$props, $this->offsets] = $data;
        $this->unserializeProperties($props);
        $this->updateParentNodeOnOffsets();
    }

    /**
     * Returns an array containing all magic properties, used when serializing
     * @return array<string, mixed>
     * @internal
     */
    abstract protected function collectProperties(): array;

    /**
     * Unserializes properties, restoring parent node link for child nodes
     *
     * @param array<string, mixed> $properties
     * @internal
     */
    abstract protected function unserializeProperties(array $properties): void;

    /**
     * Restores the parent node link for array offsets on unserializing the object
     *
     * @internal
     */
    abstract protected function updateParentNodeOnOffsets(): void;
}
