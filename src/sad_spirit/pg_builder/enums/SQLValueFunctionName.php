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

namespace sad_spirit\pg_builder\enums;

/**
 * Contains names of SQL value functions (function-like constructs that can appear without parentheses)
 */
enum SQLValueFunctionName: string
{
    use CreateFromKeywords;
    use CreateKeywords;

    case CURRENT_DATE      = 'current_date';
    case CURRENT_ROLE      = 'current_role';
    case CURRENT_USER      = 'current_user';
    case SESSION_USER      = 'session_user';
    case USER              = 'user';
    case CURRENT_CATALOG   = 'current_catalog';
    case CURRENT_SCHEMA    = 'current_schema';
    case SYSTEM_USER       = 'system_user';
    case CURRENT_TIME      = 'current_time';
    case CURRENT_TIMESTAMP = 'current_timestamp';
    case LOCALTIME         = 'localtime';
    case LOCALTIMESTAMP    = 'localtimestamp';

    private const OPTIONAL_MODIFIERS = [
        'CURRENT_TIME'      => true,
        'CURRENT_TIMESTAMP' => true,
        'LOCALTIME'         => true,
        'LOCALTIMESTAMP'    => true
    ];

    /**
     * Returns whether the function can have modifiers
     */
    public function allowsModifiers(): bool
    {
        return isset(self::OPTIONAL_MODIFIERS[$this->name]);
    }
}
