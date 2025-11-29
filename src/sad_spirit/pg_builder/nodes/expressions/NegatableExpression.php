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

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Base class for expressions that can have negative forms using NOT keyword: NOT IN, IS NOT DISTINCT FROM, etc
 *
 * @property bool $not If true, the expression should have a NOT keyword
 */
abstract class NegatableExpression extends GenericNode implements ScalarExpression
{
    /** @internal Maps to `$not` magic property, use the latter instead */
    protected bool $p_not = false;

    /** @internal Support method for `$not` magic property, use the property instead */
    public function setNot(bool $not): void
    {
        $this->p_not = $not;
    }
}
