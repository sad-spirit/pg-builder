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

use sad_spirit\pg_builder\nodes\lists\SetClauseList;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing ON CONFLICT clause of INSERT statement
 *
 * @property      string                          $action
 * @property      IndexParameters|Identifier|null $target
 * @property      SetClauseList                   $set
 * @property-read WhereOrHavingClause             $where
 */
class OnConflictClause extends GenericNode
{
    public const NOTHING = 'nothing';
    public const UPDATE  = 'update';

    private const ALLOWED_ACTIONS = [
        self::NOTHING => true,
        self::UPDATE  => true
    ];

    public function __construct(
        string $action,
        $target = null,
        SetClauseList $set = null,
        ScalarExpression $condition = null
    ) {
        $this->setAction($action);
        $this->setTarget($target);
        $this->setNamedProperty('set', $set ?? new SetClauseList());
        $this->setNamedProperty('where', new WhereOrHavingClause($condition));
    }

    public function setAction($action): void
    {
        if (!isset(self::ALLOWED_ACTIONS[$action])) {
            throw new InvalidArgumentException("Unknown ON CONFLICT action '{$action}'");
        }
        $this->props['action'] = $action;
    }

    public function setTarget($target = null): void
    {
        if (self::UPDATE === $this->props['action'] && null === $target) {
            throw new InvalidArgumentException("Target must be provided for ON CONFLICT ... DO UPDATE clause");

        } elseif (
            null !== $target
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
