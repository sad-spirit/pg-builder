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

namespace sad_spirit\pg_builder\nodes\range;

/**
 * Base class for items in FROM clause that can have a LATERAL option
 *
 * @property bool $lateral
 */
abstract class LateralFromElement extends FromElement
{
    /** @internal Maps to `$lateral` magic property, use the latter instead */
    protected bool $p_lateral = false;

    /** @internal Support method for `$lateral` magic property, use the property instead */
    public function setLateral(bool $lateral): void
    {
        $this->p_lateral = $lateral;
    }
}
