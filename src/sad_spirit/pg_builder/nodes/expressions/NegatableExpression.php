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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Base class for expressions that can have negative forms using NOT keyword: NOT IN, IS NOT DISTINCT FROM, etc
 *
 * @property bool $not If true, the expression should have a NOT keyword
 */
abstract class NegatableExpression extends GenericNode implements ScalarExpression
{
    /** @var bool */
    protected $p_not = false;

    public function setNot(bool $not): void
    {
        $this->p_not = $not;
    }
}
