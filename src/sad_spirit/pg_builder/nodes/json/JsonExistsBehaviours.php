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

/**
 * "ON ERROR" behaviour as used by json_exists() and EXISTS column definition in json_table()
 *
 * Corresponds to json_exists_error_clause_opt grammar production
 *
 * @property string|null $onError
 */
trait JsonExistsBehaviours
{
    use HasBehaviours;

    /** @var string|null */
    protected $p_onError;

    public function setOnError(?string $onError): void
    {
        /** @psalm-suppress PossiblyInvalidPropertyAssignmentValue */
        $this->setBehaviour($this->p_onError, 'ON ERROR', JsonKeywords::BEHAVIOURS_EXISTS, $onError);
    }
}
