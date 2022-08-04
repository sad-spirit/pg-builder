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

namespace sad_spirit\pg_builder\nodes\range\json;

use sad_spirit\pg_builder\Node;

/**
 * Common interface for objects representing "PLAN(...) / PLAN DEFAULT(...)" clauses in json_table()
 */
interface JsonTablePlan extends Node
{
    public const INNER = 'inner';
    public const OUTER = 'outer';

    public const UNION = 'union';
    public const CROSS = 'cross';

    public const PARENT_CHILD = [
        self::INNER,
        self::OUTER
    ];

    public const SIBLING = [
        self::UNION,
        self::CROSS
    ];
}
