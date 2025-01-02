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
 * Contains keywords that can be applied to array subexpressions when using comparison operators
 */
enum ArrayComparisonConstruct: string
{
    use CreateFromKeywords;

    case ANY  = 'any';
    case ALL  = 'all';
    case SOME = 'some';
}
