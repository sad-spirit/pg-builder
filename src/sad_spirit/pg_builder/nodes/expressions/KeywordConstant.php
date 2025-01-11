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

use sad_spirit\pg_builder\enums\ConstantName;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a constant that is an SQL keyword (true / false / null)
 */
class KeywordConstant extends Constant
{
    public function __construct(ConstantName $value)
    {
        parent::__construct($value->value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkKeywordConstant($this);
    }
}
