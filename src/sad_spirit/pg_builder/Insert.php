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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\SetTargetList,
    sad_spirit\pg_builder\nodes\QualifiedName;

/**
 * AST node representing INSERT statement
 *
 * @property-read QualifiedName $relation
 * @property-read SetTargetList $cols
 * @property      SelectCommon  $values
 * @property-read TargetList    $returning
 */
class Insert extends Statement
{
    public function __construct(QualifiedName $relation)
    {
        parent::__construct();

        $this->setNamedProperty('relation', $relation);
        $this->props['cols']      = new SetTargetList();
        $this->props['values']    = null;
        $this->props['returning'] = new TargetList();

        $this->props['cols']->setParentNode($this);
        $this->props['returning']->setParentNode($this);
    }

    public function setValues(SelectCommon $values = null)
    {
        $this->setNamedProperty('values', $values);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkInsertStatement($this);
    }
}
