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

namespace sad_spirit\pg_builder\nodes;

/**
 * Trait for descendants of GenericNodeList that expose properties alongside array offsets
 *
 * Serializes / unserializes both array instead of just $offsets
 *
 * @psalm-require-extends GenericNode
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

    abstract protected function collectProperties(): array;

    /**
     * @param array<string, mixed> $properties
     */
    abstract protected function unserializeProperties(array $properties): void;

    abstract protected function updateParentNodeOnOffsets(): void;
}
