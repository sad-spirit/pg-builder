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

use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing the json_array() expression with a list of expressions as argument
 *
 * @psalm-property JsonFormattedValueList $arguments
 *
 * @property JsonFormattedValueList|JsonFormattedValue[] $arguments
 */
class JsonArrayValueList extends JsonArray
{
    use AbsentOnNullProperty;

    /** @var JsonFormattedValueList */
    protected $p_arguments;

    public function __construct(
        ?JsonFormattedValueList $arguments = null,
        ?bool $absentOnNull = null,
        ?JsonReturning $returning = null
    ) {
        parent::__construct($returning);

        $this->p_arguments = $arguments ?? new JsonFormattedValueList();
        $this->p_arguments->setParentNode($this);

        $this->setAbsentOnNull($absentOnNull);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonArrayValueList($this);
    }
}
