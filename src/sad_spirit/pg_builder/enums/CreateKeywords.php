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

use sad_spirit\pg_builder\Keyword;

/**
 * Helper methods for converting enums into {@see Keyword} cases
 *
 * @psalm-require-implements \BackedEnum
 */
trait CreateKeywords
{
    /**
     * Returns an array of {@see Keyword} cases based on cases of current enum
     *
     * @return Keyword[]
     */
    public static function toKeywords(): array
    {
        return \array_map(fn (self $case): Keyword => Keyword::from($case->value), self::cases());
    }
}
