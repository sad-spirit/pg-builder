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
 * Contains possible predicates for `IS` expression
 */
enum IsPredicate: string
{
    use CreateFromKeywords;

    case NULL            = 'null';
    case TRUE            = 'true';
    case FALSE           = 'false';
    case UNKNOWN         = 'unknown';
    case DOCUMENT        = 'document';
    case NORMALIZED      = 'normalized';
    case NFC_NORMALIZED  = 'nfc normalized';
    case NFD_NORMALIZED  = 'nfd normalized';
    case NFKC_NORMALIZED = 'nfkc normalized';
    case NFKD_NORMALIZED = 'nfkd normalized';
}
