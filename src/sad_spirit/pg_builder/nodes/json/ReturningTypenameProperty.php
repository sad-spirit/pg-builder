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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\TypeName;

/**
 * Adds $returning property that maps to "RETURNING type_name" clause used in JSON expressions
 *
 * If syntax allows FORMAT after type_name then JsonReturning and ReturningProperty should be used
 *
 * @property TypeName|null $returning
 */
trait ReturningTypenameProperty
{
    /** @var TypeName|null */
    protected $p_returning = null;

    public function setReturning(?TypeName $returning): void
    {
        $this->setProperty($this->p_returning, $returning);
    }
}
