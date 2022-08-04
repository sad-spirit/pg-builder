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
 * Keywords used in traits for JSON query functions and column definitions in json_table()
 */
final class JsonKeywords
{
    public const WRAPPER_WITHOUT       = 'without';
    public const WRAPPER_CONDITIONAL   = 'with conditional';
    public const WRAPPER_UNCONDITIONAL = 'with unconditional';

    public const WRAPPERS = [
        self::WRAPPER_WITHOUT,
        self::WRAPPER_CONDITIONAL,
        self::WRAPPER_UNCONDITIONAL
    ];

    public const BEHAVIOUR_TRUE = 'true';
    public const BEHAVIOUR_ERROR = 'error';
    public const BEHAVIOUR_EMPTY_OBJECT = 'empty object';
    public const BEHAVIOUR_UNKNOWN = 'unknown';
    public const BEHAVIOUR_FALSE = 'false';
    public const BEHAVIOUR_NULL = 'null';
    public const BEHAVIOUR_EMPTY_ARRAY = 'empty array';

    public const BEHAVIOURS_EXISTS = [
        self::BEHAVIOUR_UNKNOWN,
        self::BEHAVIOUR_TRUE,
        self::BEHAVIOUR_FALSE,
        self::BEHAVIOUR_ERROR
    ];

    public const BEHAVIOURS_QUERY = [
        self::BEHAVIOUR_ERROR,
        self::BEHAVIOUR_NULL,
        self::BEHAVIOUR_EMPTY_ARRAY,
        self::BEHAVIOUR_EMPTY_OBJECT
    ];

    public const BEHAVIOURS_VALUE = [
        self::BEHAVIOUR_ERROR,
        self::BEHAVIOUR_NULL
    ];
}
