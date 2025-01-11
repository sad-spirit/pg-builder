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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a numeric constant
 */
class NumericConstant extends Constant
{
    private const REGEXP = <<<'REGEXP'
{^
    -?                                                                          # allow unary minus
    (?> 
        0[bB](?: _?[01] )+ | 0[oO](?: _?[0-7] )+ | 0[xX](?: _?[0-9a-fA-F] )+ |  # non-decimal integer literals 
        (?: \d(?: _?\d )* (?: \. (?: \d(?: _?\d )* )? )? | \.\d(?: _?\d )*)     # decimal literal
        (?: [Ee][-+]?\d(?: _? \d)* )?                                           # followed by possible exponent
    )
$}x
REGEXP;


    public function __construct(string $value)
    {
        if (
            !\is_numeric($value)
            && !\preg_match(self::REGEXP, $value)
        ) {
            throw new InvalidArgumentException(self::class . " expects a numeric string");
        }
        parent::__construct($value);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkNumericConstant($this);
    }
}
