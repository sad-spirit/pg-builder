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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\SetTargetList,
    sad_spirit\pg_builder\nodes\range\InsertTarget,
    sad_spirit\pg_builder\nodes\OnConflictClause,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing INSERT statement
 *
 * @property-read InsertTarget      $relation
 * @property      SetTargetList     $cols
 * @property      SelectCommon      $values
 * @property      string|null       $overriding
 * @property      OnConflictClause  $onConflict
 * @property      TargetList        $returning
 */
class Insert extends Statement
{
    public function __construct(InsertTarget $relation)
    {
        parent::__construct();

        $this->setNamedProperty('relation', $relation);
        $this->props['cols']       = new SetTargetList();
        $this->props['values']     = null;
        $this->props['returning']  = new TargetList();
        $this->props['onConflict'] = null;
        $this->props['overriding'] = null;

        $this->props['cols']->setParentNode($this);
        $this->props['returning']->setParentNode($this);
    }

    public function setValues(SelectCommon $values = null)
    {
        $this->setNamedProperty('values', $values);
    }

    public function setOnConflict($onConflict = null)
    {
        if (is_string($onConflict)) {
            if (!($parser = $this->getParser())) {
                throw new InvalidArgumentException("Passed a string as ON CONFLICT clause without a Parser available");
            }
            $onConflict = $parser->parseOnConflict($onConflict);
        }
        if (null !== $onConflict && !($onConflict instanceof OnConflictClause)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of OnConflictClause, %s given',
                __METHOD__, is_object($onConflict) ? 'object(' . get_class($onConflict) . ')' : gettype($onConflict)
            ));
        }
        $this->setNamedProperty('onConflict', $onConflict);
    }

    public function setOverriding($overriding = null)
    {
        if (null !== $overriding && !in_array($overriding, array('user', 'system'))) {
            throw new InvalidArgumentException("Unknown override kind '{$overriding}'");
        }
        $this->props['overriding'] = $overriding;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkInsertStatement($this);
    }
}
