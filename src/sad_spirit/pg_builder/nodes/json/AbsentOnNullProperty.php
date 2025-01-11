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
 * Adds $absentOnNull property that maps to "(ABSENT|NULL) ON NULL" clause used in JSON expressions
 *
 * @property bool|null $absentOnNull
 */
trait AbsentOnNullProperty
{
    protected ?bool $p_absentOnNull = null;

    public function setAbsentOnNull(?bool $absentOnNull): void
    {
        $this->p_absentOnNull = $absentOnNull;
    }
}
