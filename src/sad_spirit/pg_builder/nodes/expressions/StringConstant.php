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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\enums\StringConstantType;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a string constant (including bit-strings)
 */
class StringConstant extends Constant
{
    public function __construct(
        string $value,
        public readonly StringConstantType $type = StringConstantType::CHARACTER
    ) {
        if (StringConstantType::BINARY === $type && \preg_match('/[^01]/', $value, $m)) {
            throw new InvalidArgumentException("Invalid binary digit {$m[0]}");
        }
        if (StringConstantType::HEXADECIMAL === $type && \preg_match('/[^0-9a-fA-F]/', $value, $m)) {
            throw new InvalidArgumentException("Invalid hexadecimal digit {$m[0]}");
        }

        parent::__construct($value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkStringConstant($this);
    }

    public function __serialize(): array
    {
        return [$this->type, $this->value];
    }

    public function __unserialize(array $data): void
    {
        [$this->type, $value] = $data;
        parent::__unserialize([$value]);
    }
}
