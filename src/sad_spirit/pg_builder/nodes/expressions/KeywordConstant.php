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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a constant that is an SQL keyword (true / false / null)
 */
class KeywordConstant extends Constant
{
    public const NULL  = 'null';
    public const TRUE  = 'true';
    public const FALSE = 'false';

    public function __construct(string $value)
    {
        if (self::NULL !== $value && self::TRUE !== $value && self::FALSE !== $value) {
            throw new InvalidArgumentException("Unknown keyword '{$value}' for a constant");
        }
        $this->p_value = $value;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkKeywordConstant($this);
    }
}
