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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents an array subscript [foo] or array slice [foo:bar] operation
 *
 * @property ScalarExpression|null $lower
 * @property ScalarExpression|null $upper
 * @property bool                  $isSlice
 */
class ArrayIndexes extends Node
{
    public function __construct(
        ScalarExpression $lower = null,
        ScalarExpression $upper = null,
        $isSlice = false
    ) {
        $this->setNamedProperty('lower', $lower);
        $this->setNamedProperty('upper', $upper);
        $this->setIsSlice($isSlice);
    }

    public function setLower(ScalarExpression $lower = null)
    {
        $this->setNamedProperty('lower', $lower);
    }

    public function setUpper(ScalarExpression $upper = null)
    {
        $this->setNamedProperty('upper', $upper);
    }

    public function setIsSlice($isSlice)
    {
        $this->props['isSlice'] = (bool)$isSlice;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkArrayIndexes($this);
    }
}
