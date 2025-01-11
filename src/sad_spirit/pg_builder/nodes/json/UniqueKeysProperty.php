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

namespace sad_spirit\pg_builder\nodes\json;

/**
 * Adds $uniqueKeys property that maps to "(WITH|WITHOUT) UNIQUE [KEYS]" clause used in JSON expressions
 *
 * @property bool|null $uniqueKeys
 */
trait UniqueKeysProperty
{
    protected ?bool $p_uniqueKeys;

    public function setUniqueKeys(?bool $uniqueKeys): void
    {
        $this->p_uniqueKeys = $uniqueKeys;
    }
}
