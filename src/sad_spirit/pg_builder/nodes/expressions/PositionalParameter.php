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
 * Represents a positional '$1' query parameter
 *
 * @property-read int $position
 */
class PositionalParameter extends Parameter
{
    /** @var int */
    protected $p_position;

    protected $propertyNames = [
        'position' => 'p_position'
    ];

    public function __construct(int $position)
    {
        if (0 >= $position) {
            throw new InvalidArgumentException("Position should be positive");
        }
        $this->p_position = $position;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkPositionalParameter($this);
    }

    public function serialize(): string
    {
        return (string)$this->p_position;
    }

    public function __serialize(): array
    {
        return [$this->p_position];
    }

    public function unserialize($serialized)
    {
        $this->p_position = (int)$serialized;
    }

    public function __unserialize(array $data): void
    {
        [$this->p_position] = $data;
    }
}
