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
 * Contains possible locking strengths for locking clause: `SELECT ... FOR [strength]`
 */
enum LockingStrength: string
{
    use CreateFromKeywords;

    case UPDATE        = 'update';
    case NO_KEY_UPDATE = 'no key update';
    case SHARE         = 'share';
    case KEY_SHARE     = 'key share';
}
