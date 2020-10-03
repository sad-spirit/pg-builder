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

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    lists\FromList,
    lists\TargetList,
    range\UpdateOrDeleteTarget,
    WhereOrHavingClause
};

/**
 * AST node representing DELETE statement
 *
 * @property-read UpdateOrDeleteTarget  $relation
 * @property      FromList              $using
 * @property-read WhereOrHavingClause   $where
 * @property      TargetList            $returning
 */
class Delete extends Statement
{
    public function __construct(UpdateOrDeleteTarget $relation)
    {
        parent::__construct();

        $this->setNamedProperty('relation', $relation);
        $this->setNamedProperty('using', new FromList());
        $this->setNamedProperty('returning', new TargetList());
        $this->setNamedProperty('where', new WhereOrHavingClause());
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkDeleteStatement($this);
    }
}
