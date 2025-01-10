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

namespace sad_spirit\pg_builder\tests;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\{
    Keyword,
    Lexer,
    Parser
};

class ParseTargetListTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testBareLabelKeywords(): void
    {
        $parts = \array_map(
            fn (Keyword $keyword): string => 'null ' . $keyword->value,
            \array_filter(Keyword::cases(), fn (Keyword $keyword): bool => $keyword->isBareLabel())
        );
        $parts[] = 'false or false or';
        $parts[] = 'true and true and';
        $parts[] = 'foo is distinct from bar collate';

        (new Parser(new Lexer()))->parseTargetList(\implode(', ', $parts));
    }
}
