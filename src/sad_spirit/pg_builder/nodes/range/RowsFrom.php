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

use sad_spirit\pg_builder\nodes\lists\RowsFromList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a ROWS FROM() construct in FROM clause (PostgreSQL 9.4+)
 *
 * @property RowsFromList $functions
 */
class RowsFrom extends FunctionFromElement
{
    /** @var RowsFromList */
    protected $p_functions;

    public function __construct(RowsFromList $functions)
    {
        $this->generatePropertyNames();

        $this->p_functions = $functions;
        $this->p_functions->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkRowsFrom($this);
    }
}
