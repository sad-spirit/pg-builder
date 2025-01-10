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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression,
    WindowDefinition
};

/**
 * Base class for nodes representing JSON aggregate functions (json_arrayagg / json_objectagg)
 *
 * @property ScalarExpression|null $filter
 * @property WindowDefinition|null $over
 */
abstract class JsonAggregate extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;
    use AbsentOnNullProperty;
    use ReturningProperty;

    protected ?ScalarExpression $p_filter = null;
    protected ?WindowDefinition $p_over = null;

    public function __construct(
        ?bool $absentOnNull,
        ?JsonReturning $returning,
        ?ScalarExpression $filter,
        ?WindowDefinition $over
    ) {
        $this->generatePropertyNames();

        $this->p_absentOnNull = $absentOnNull;
        if (null !== $returning) {
            $this->p_returning = $returning;
            $this->p_returning->setParentNode($this);
        }
        if (null !== $filter) {
            $this->p_filter = $filter;
            $this->p_filter->setParentNode($this);
        }
        if (null !== $over) {
            $this->p_over = $over;
            $this->p_over->setParentNode($this);
        }
    }

    public function setFilter(?ScalarExpression $filter): void
    {
        $this->setProperty($this->p_filter, $filter);
    }

    public function setOver(?WindowDefinition $over): void
    {
        $this->setProperty($this->p_over, $over);
    }
}
