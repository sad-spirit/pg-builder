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
use sad_spirit\pg_builder\Token;
use sad_spirit\pg_builder\TokenType;

/**
 * Base class for lexer Tokens
 */
abstract class GenericToken implements Token
{
    public function __construct(private readonly int $position)
    {
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getKeyword(): ?Keyword
    {
        return null;
    }

    public function matches(TokenType $type, array|string|null $values = null): bool
    {
        if ($type->value !== ($type->value & $this->getType()->value)) {
            return false;
        } elseif (null === $values) {
            return true;
        } else {
            return $this->getValue() === $values
                || (\is_array($values) && \in_array($this->getValue(), $values, true));
        }
    }

    public function matchesKeyword(Keyword ...$keywords): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return \sprintf(
            "%s '%s' at position %d",
            $this->getType()->toString(),
            $this->getValue(),
            $this->position
        );
    }
}
