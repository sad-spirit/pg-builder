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

use sad_spirit\pg_builder\enums\IntervalMask;
use sad_spirit\pg_builder\nodes\lists\TypeModifierList;

/**
 * Represents a type name for INTERVAL type
 *
 * A separate class is required as interval may have a mask specified, like in
 * <code>
 * interval '2 days 2 seconds' minute to second
 * </code>
 * While this mask can be converted to an integer type modifier and casting can be
 * done to underlying type pg_catalog.interval, this is too implementation-specific.
 * So we keep the mask in text form and use the standard INTERVAL type name.
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
