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

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\lists\SetClauseList;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing ON CONFLICT clause of INSERT statement
 *
 * @psalm-property SetClauseList $set
 *
 * @property      string                                              $action
 * @property      IndexParameters|Identifier|null                     $target
 * @property      SetClauseList|SingleSetClause[]|MultipleSetClause[] $set
 * @property-read WhereOrHavingClause                                 $where
 */
class OnConflictClause extends GenericNode
{
    public const NOTHING = 'nothing';
    public const UPDATE  = 'update';

    private const ALLOWED_ACTIONS = [
        self::NOTHING => true,
        self::UPDATE  => true
    ];

    /** @var string */
    protected $p_action;
    /** @var IndexParameters|Identifier|null */
    protected $p_target;
    /** @var SetClauseList */
    protected $p_set;
    /** @var WhereOrHavingClause */
    protected $p_where;

    /**
     * OnConflictClause constructor
     *
     * @param string                          $action
     * @param IndexParameters|Identifier|null $target
     * @param SetClauseList|null              $set
     * @param ScalarExpression|null           $condition
     */
    public function __construct(
        string $action,
        Node $target = null,
        SetClauseList $set = null,
        ScalarExpression $condition = null
    ) {
        $this->generatePropertyNames();
        $this->setAction($action);
        $this->setTarget($target);

        $this->p_set = $set ?? new SetClauseList();
        $this->p_set->setParentNode($this);

        $this->p_where = new WhereOrHavingClause($condition);
        $this->p_where->setParentNode($this);
    }

    public function setAction(string $action): void
    {
        if (!isset(self::ALLOWED_ACTIONS[$action])) {
            throw new InvalidArgumentException("Unknown ON CONFLICT action '{$action}'");
        }
        $this->p_action = $action;
    }

    /**
     * Sets the Node for conflicting constraint name / index parameters
     *
     * @param IndexParameters|Identifier|null $target
     */
    public function setTarget(Node $target = null): void
    {
        if (self::UPDATE === $this->p_action && null === $target) {
            throw new InvalidArgumentException("Target must be provided for ON CONFLICT ... DO UPDATE clause");

        } elseif (
            null !== $target
            && !($target instanceof Identifier) && !($target instanceof IndexParameters)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Target for ON CONFLICT clause can be either a constraint Identifier or IndexParameters, %s given',
                get_class($target)
            ));
        }
        $this->setProperty($this->p_target, $target);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOnConflictClause($this);
    }
}
