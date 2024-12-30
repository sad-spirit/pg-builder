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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TokenType;

/**
 * Token having a type and a string value
 */
final class StringToken extends GenericToken
{
    private TokenType $type;
    private string $value;

    public function __construct(TokenType $type, string $value, int $position)
    {
        if ($type->needsUtf8Check() && !\preg_match('//u', $value)) {
            throw new InvalidArgumentException(\sprintf(
                "Invalid UTF-8 in %s at position %d of input: %s",
                $type->toString(),
                $position,
                $value
            ));
        }

        $this->type = $type;
        $this->value = $value;

        parent::__construct($position);
    }

    public function getType(): TokenType
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
