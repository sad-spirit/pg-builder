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

namespace sad_spirit\pg_builder\tokens;

use sad_spirit\pg_builder\Keyword;
use sad_spirit\pg_builder\TokenType;

/**
 * Token representing a keyword
 */
final class KeywordToken extends GenericToken
{
    public function __construct(private readonly Keyword $keyword, int $position)
    {
        parent::__construct($position);
    }

    public function getType(): TokenType
    {
        return $this->keyword->getType();
    }

    public function getValue(): string
    {
        return $this->keyword->value;
    }

    public function getKeyword(): Keyword
    {
        return $this->keyword;
    }

    public function matchesKeyword(Keyword ...$keywords): bool
    {
        return [$this->keyword] === $keywords
            || \in_array($this->keyword, $keywords, true);
    }
}
