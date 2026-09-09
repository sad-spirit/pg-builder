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
 * Contains possible types for an ElementPattern (used in graph_table())
 */
enum GraphElementPatternKind
{
    case VERTEX;
    case EDGE_LEFT;
    case EDGE_RIGHT;
    case EDGE_ANY;

    /**
     * Returns string representation for an abbreviated pattern (with no filler parts present)
     */
    public function abbreviatedPattern(): string
    {
        return match ($this) {
            self::VERTEX     => '()',
            self::EDGE_ANY   => '-',
            self::EDGE_LEFT  => '<-',
            self::EDGE_RIGHT => '->'
        };
    }

    /**
     * Returns borders to use around the filler parts
     *
     * @return array{string, string}
     */
    public function patternBorders(): array
    {
        return match ($this) {
            self::VERTEX     => ['(',   ')'],
            self::EDGE_ANY   => ['-[',  ']-'],
            self::EDGE_LEFT  => ['<-[', ']-'],
            self::EDGE_RIGHT => ['-[',  ']->']
        };
    }
}
