<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node generated for frame_bound grammar production in window specifications
 *
 * @property-read string                $direction
 * @property      ScalarExpression|null $value
 */
class WindowFrameBound extends GenericNode
{
    public const PRECEDING   = 'preceding';
    public const FOLLOWING   = 'following';
    public const CURRENT_ROW = 'current row';

    private const ALLOWED_DIRECTIONS = [
        self::PRECEDING   => true,
        self::FOLLOWING   => true,
        self::CURRENT_ROW => true
    ];

    /** @var string */
    protected $p_direction;
    /** @var ScalarExpression|null */
    protected $p_value;

    public function __construct(string $direction, ScalarExpression $value = null)
    {
        if (!isset(self::ALLOWED_DIRECTIONS[$direction])) {
            throw new InvalidArgumentException("Unknown window frame direction '{$direction}'");
        }

        $this->generatePropertyNames();
        $this->p_direction = $direction;
        $this->setValue($value);
    }

    public function setValue(ScalarExpression $value = null): void
    {
        if (!is_null($value) && !in_array($this->p_direction, [self::PRECEDING, self::FOLLOWING])) {
            throw new InvalidArgumentException("Value can only be set for PRECEDING or FOLLOWING direction");
        }
        $this->setProperty($this->p_value, $value);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkWindowFrameBound($this);
    }
}
