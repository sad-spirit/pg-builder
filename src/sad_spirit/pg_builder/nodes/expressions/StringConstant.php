<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
        if (StringConstantType::BINARY === $type && preg_match('/[^01]/', $value, $m)) {
            throw new InvalidArgumentException("Invalid binary digit {$m[0]}");
        }
        if (StringConstantType::HEXADECIMAL === $type && preg_match('/[^0-9a-fA-F]/', $value, $m)) {
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
