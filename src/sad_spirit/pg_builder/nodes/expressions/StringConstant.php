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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a string constant (including bit-strings)
 *
 * @property-read string $type String type, one of TYPE_* constants
 */
class StringConstant extends Constant
{
    public const TYPE_CHARACTER   = 'c';
    public const TYPE_BINARY      = 'b';
    public const TYPE_HEXADECIMAL = 'x';

    /** @var string */
    protected $p_type;

    protected $propertyNames = [
        'value' => 'p_value',
        'type'  => 'p_type'
    ];

    public function __construct(string $value, string $type = self::TYPE_CHARACTER)
    {
        if (self::TYPE_CHARACTER !== $type && self::TYPE_BINARY !== $type && self::TYPE_HEXADECIMAL !== $type) {
            throw new InvalidArgumentException("Unknown string type '{$type}'");
        }
        if (self::TYPE_BINARY === $type && preg_match('/[^01]/', $value, $m)) {
            throw new InvalidArgumentException("Invalid binary digit {$m[0]}");
        }
        if (self::TYPE_HEXADECIMAL === $type && preg_match('/[^0-9a-fA-F]/', $value, $m)) {
            throw new InvalidArgumentException("Invalid hexadecimal digit {$m[0]}");
        }

        $this->p_value = $value;
        $this->p_type  = $type;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkStringConstant($this);
    }

    public function serialize(): string
    {
        return serialize([$this->p_type, $this->p_value]);
    }

    public function __serialize(): array
    {
        return [$this->p_type, $this->p_value];
    }

    public function unserialize($serialized)
    {
        [$this->p_type, $this->p_value] = unserialize($serialized);
    }

    public function __unserialize(array $data): void
    {
        [$this->p_type, $this->p_value] = $data;
    }
}
