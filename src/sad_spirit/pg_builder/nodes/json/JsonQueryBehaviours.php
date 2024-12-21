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

use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * "ON EMPTY" / "ON ERROR" behaviours as used by json_query() and formatted column definition in json_table()
 *
 * Corresponds to json_query_on_behavior_clause_opt grammar production
 *
 * @property string|ScalarExpression|null $onEmpty
 * @property string|ScalarExpression|null $onError
 */
trait JsonQueryBehaviours
{
    use HasBehaviours;

    /** @var string|ScalarExpression|null */
    protected $p_onEmpty = null;
    /** @var string|ScalarExpression|null */
    protected $p_onError = null;

    /**
     * Sets the value for ON EMPTY clause
     *
     * @param string|ScalarExpression|null $onEmpty an instance of ScalarExpression corresponds to "DEFAULT ..." value
     * @return void
     */
    public function setOnEmpty($onEmpty): void
    {
        $this->setBehaviour($this->p_onEmpty, 'ON EMPTY', JsonKeywords::BEHAVIOURS_QUERY, $onEmpty);
    }

    /**
     * Sets the value for ON ERROR clause
     *
     * @param string|ScalarExpression|null $onError an instance of ScalarExpression corresponds to "DEFAULT ..." value
     * @return void
     */
    public function setOnError($onError): void
    {
        $this->setBehaviour($this->p_onError, 'ON ERROR', JsonKeywords::BEHAVIOURS_QUERY, $onError);
    }
}
