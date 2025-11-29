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
 * Adds $returning property that maps to "RETURNING ..." clause used in JSON expressions
 *
 * @property JsonReturning|null $returning
 */
trait ReturningProperty
{
    /** @internal Maps to `$returning` magic property, use the latter instead */
    protected ?JsonReturning $p_returning = null;

    /** @internal Support method for `$returning` magic property, use the property instead */
    public function setReturning(?JsonReturning $returning): void
    {
        $this->setProperty($this->p_returning, $returning);
    }
}
