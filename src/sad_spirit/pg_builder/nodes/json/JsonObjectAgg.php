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

use sad_spirit\pg_builder\{
    nodes\ScalarExpression,
    nodes\WindowDefinition,
    TreeWalker
};

/**
 * Represents json_objectagg() expression
 *
 * @property JsonKeyValue $keyValue
 * @property bool|null    $uniqueKeys
 */
class JsonObjectAgg extends JsonAggregate
{
    /** @var JsonKeyValue */
    protected $p_keyValue;
    /** @var bool|null */
    protected $p_uniqueKeys;

    public function __construct(
        JsonKeyValue $keyValue,
        ?bool $absentOnNull = null,
        ?bool $uniqueKeys = null,
        ?JsonReturning $returning = null,
        ?ScalarExpression $filter = null,
        ?WindowDefinition $over = null
    ) {
        parent::__construct($absentOnNull, $returning, $filter, $over);

        $this->p_keyValue = $keyValue;
        $this->p_keyValue->setParentNode($this);

        $this->p_uniqueKeys = $uniqueKeys;
    }

    public function setKeyValue(JsonKeyValue $keyValue): void
    {
        $this->setRequiredProperty($this->p_keyValue, $keyValue);
    }

    public function setUniqueKeys(?bool $uniqueKeys): void
    {
        $this->p_uniqueKeys = $uniqueKeys;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonObjectAgg($this);
    }
}
