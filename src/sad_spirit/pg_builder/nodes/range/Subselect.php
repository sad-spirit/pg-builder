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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a subselect in FROM clause
 *
 * @property SelectCommon $query
 */
class Subselect extends LateralFromElement
{
    /** @var SelectCommon */
    protected $p_query;

    public function __construct(SelectCommon $query)
    {
        $this->generatePropertyNames();

        $this->p_query = $query;
        $this->p_query->setParentNode($this);
    }

    public function setQuery(SelectCommon $query): void
    {
        $this->setRequiredProperty($this->p_query, $query);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRangeSubselect($this);
    }
}
