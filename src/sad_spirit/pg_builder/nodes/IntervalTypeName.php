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

use sad_spirit\pg_builder\enums\IntervalMask;
use sad_spirit\pg_builder\nodes\lists\TypeModifierList;

/**
 * Represents a type name for INTERVAL type
 *
 * A separate class is required as interval may have a mask specified, like in
 * ```SQL
 * interval '2 days 2 seconds' minute to second
 * ```
 *
 * While this mask can be converted to an integer type modifier and casting can be
 * done to underlying type `pg_catalog.interval`, this is too implementation-specific.
 * So we keep the mask in text form and use the standard `INTERVAL` type name.
 *
 * @property ?IntervalMask $mask
 */
class IntervalTypeName extends TypeName
{
    protected ?IntervalMask $p_mask = null;

    public function __construct(?TypeModifierList $typeModifiers = null)
    {
        parent::__construct(new QualifiedName('pg_catalog', 'interval'), $typeModifiers);
    }

    public function setMask(?IntervalMask $mask): void
    {
        $this->p_mask = $mask;
    }
}
