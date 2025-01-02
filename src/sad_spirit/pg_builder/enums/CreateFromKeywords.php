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
 * Helper methods for creating enums from {@see Keyword} enum cases
 *
 * @psalm-require-implements \BackedEnum
 */
trait CreateFromKeywords
{
    /**
     * Translates a string composed of space-separated values of given `Keyword`s into the corresponding `Enum` case
     *
     * The underlying `from()` method will throw a `ValueError` if there is no matching case
     */
    public static function fromKeywords(Keyword ...$keywords): static
    {
        return static::from(\implode(' ', \array_map(fn (Keyword $keyword) => $keyword->value, $keywords)));
    }

    /**
     * Translates a string composed of space-separated values of given `Keyword`s into the corresponding `Enum` case
     *
     * Will return `null` if there is no matching case
     */
    public static function tryFromKeywords(Keyword ...$keywords): ?static
    {
        return static::tryFrom(\implode(' ', \array_map(fn (Keyword $keyword) => $keyword->value, $keywords)));
    }
}
