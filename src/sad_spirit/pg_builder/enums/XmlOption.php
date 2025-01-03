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
 * Contains allowed values for `XML OPTION` setting in `xmlparse()` and `xmlserialize()`
 */
enum XmlOption: string
{
    use CreateFromKeywords;

    case DOCUMENT = 'document';
    case CONTENT  = 'content';
}
