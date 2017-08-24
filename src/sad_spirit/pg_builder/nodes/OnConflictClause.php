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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\lists\SetClauseList,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing ON CONFLICT clause of INSERT statement
 *
 * @property      string                          $action
 * @property      IndexParameters|Identifier|null $target
 * @property      SetClauseList                   $set
 * @property-read WhereOrHavingClause             $where
 */
class OnConflictClause extends Node
{
    public function __construct($action, $target = null, SetClauseList $set = null, ScalarExpression $condition = null)
    {
        $this->setAction($action);
        $this->setTarget($target);
        $this->props['set']   = new SetClauseList();
        $this->props['where'] = new WhereOrHavingClause($condition);

        $this->props['set']->setParentNode($this);
        $this->props['where']->setParentNode($this);

        if (null !== $set) {
            $this->props['set']->replace($set);
        }
    }

    public function setAction($action)
    {
        if (!in_array($action, array('nothing', 'update'), true)) {
            throw new InvalidArgumentException("Unknown ON CONFLICT action '{$action}'");
        }
        $this->props['action'] = $action;
    }

    public function setTarget($target = null)
    {
        if ('update' === $this->props['action'] && null === $target) {
            throw new InvalidArgumentException("Target must be provided for ON CONFLICT ... DO UPDATE clause");

        } elseif (null !== $target
                  && !($target instanceof Identifier) && !($target instanceof IndexParameters)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Target for ON CONFLICT clause can be either a constraint Identifier or IndexParameters, %s given',
                is_object($target) ? 'object(' . get_class($target) . ')' : gettype($target)
            ));
        }
        $this->setNamedProperty('target', $target);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOnConflictClause($this);
    }
}