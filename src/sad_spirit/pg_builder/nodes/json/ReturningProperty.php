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

namespace sad_spirit\pg_builder\nodes\json;

/**
 * Adds $returning property that maps to "RETURNING ..." clause used in JSON expressions
 *
 * @property JsonReturning|null $returning
 */
trait ReturningProperty
{
    protected ?JsonReturning $p_returning = null;

    public function setReturning(?JsonReturning $returning): void
    {
        $this->setProperty($this->p_returning, $returning);
    }
}
