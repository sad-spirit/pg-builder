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

use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents an array subscript [foo] or array slice [foo:bar] operation
 *
 * @property ScalarExpression|null $lower
 * @property ScalarExpression|null $upper
 * @property bool                  $isSlice
 */
class ArrayIndexes extends GenericNode
{
    public function __construct(
        ScalarExpression $lower = null,
        ScalarExpression $upper = null,
        bool $isSlice = false
    ) {
        $this->setNamedProperty('lower', $lower);
        $this->setNamedProperty('upper', $upper);
        $this->setIsSlice($isSlice);
    }

    public function setLower(ScalarExpression $lower = null): void
    {
        $this->setNamedProperty('lower', $lower);
    }

    public function setUpper(ScalarExpression $upper = null): void
    {
        $this->setNamedProperty('upper', $upper);
    }

    public function setIsSlice(bool $isSlice): void
    {
        $this->props['isSlice'] = $isSlice;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkArrayIndexes($this);
    }
}
