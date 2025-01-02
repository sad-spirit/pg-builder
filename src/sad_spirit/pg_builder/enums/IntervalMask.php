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
 * Contains possible masks for interval type specifications
 */
enum IntervalMask: string
{
    use CreateFromKeywords;

    case YEAR  = 'year';
    case MONTH  = 'month';
    case DAY    = 'day';
    case HOUR   = 'hour';
    case MINUTE = 'minute';
    case SECOND = 'second';
    case YTM    = 'year to month';
    case DTH    = 'day to hour';
    case DTM    = 'day to minute';
    case DTS    = 'day to second';
    case HTM    = 'hour to minute';
    case HTS    = 'hour to second';
    case MTS    = 'minute to second';
}
