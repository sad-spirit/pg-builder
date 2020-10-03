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

namespace sad_spirit\pg_builder\nodes\range;

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a subselect in FROM clause
 *
 * @property SelectCommon $query
 * @property bool         $lateral
 */
class Subselect extends FromElement
{
    public function __construct(SelectCommon $query)
    {
        $this->setQuery($query);
        $this->props['lateral'] = false;
    }

    public function setQuery(SelectCommon $query): void
    {
        $this->setNamedProperty('query', $query);
    }

    public function setLateral(bool $lateral): void
    {
        $this->props['lateral'] = $lateral;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRangeSubselect($this);
    }
}
