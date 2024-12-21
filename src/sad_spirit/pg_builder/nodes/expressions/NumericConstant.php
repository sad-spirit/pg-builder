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
            throw new InvalidArgumentException(__CLASS__ . " expects a numeric string");
        }
        $this->p_value = $value;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkNumericConstant($this);
    }
}
