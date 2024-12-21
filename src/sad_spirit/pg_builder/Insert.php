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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

use sad_spirit\pg_builder\nodes\{
    lists\TargetList,
    lists\SetTargetList,
    range\InsertTarget,
    OnConflictClause,
    SetTargetElement,
    TargetElement
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing INSERT statement
 *
 * @psalm-property SetTargetList $cols
 * @psalm-property TargetList    $returning
 *
 * @property-read InsertTarget                     $relation
 * @property      SetTargetList|SetTargetElement[] $cols
 * @property      SelectCommon|null                $values
 * @property      string|null                      $overriding
 * @property      OnConflictClause|null            $onConflict
 * @property      TargetList|TargetElement[]       $returning
 */
class Insert extends Statement
{
    public const OVERRIDING_USER   = 'user';
    public const OVERRIDING_SYSTEM = 'system';

    public const ALLOWED_OVERRIDING = [
        self::OVERRIDING_SYSTEM => true,
        self::OVERRIDING_USER   => true
    ];

    /** @var InsertTarget */
    protected $p_relation;
    /** @var SetTargetList */
    protected $p_cols;
    /** @var SelectCommon|null */
    protected $p_values;
    /** @var string|null */
    protected $p_overriding;
    /** @var OnConflictClause|null */
    protected $p_onConflict;
    /** @var TargetList */
    protected $p_returning;

    public function __construct(InsertTarget $relation)
    {
        parent::__construct();

        $relation->setParentNode($this);
        $this->p_relation = $relation;

        $this->p_cols      = new SetTargetList();
        $this->p_returning = new TargetList();

        $this->p_cols->parentNode      = $this;
        $this->p_returning->parentNode = $this;
    }

    public function setValues(SelectCommon $values = null): void
    {
        $this->setProperty($this->p_values, $values);
    }

    /**
     * Sets the Node representing 'ON CONFLICT' clause
     *
     * @param string|OnConflictClause|null $onConflict
     */
    public function setOnConflict($onConflict = null): void
    {
        if (is_string($onConflict)) {
            $onConflict = $this->getParserOrFail('ON CONFLICT clause')->parseOnConflict($onConflict);
        }
        if (null !== $onConflict && !($onConflict instanceof OnConflictClause)) {
            throw new InvalidArgumentException(sprintf(
                '%s expects an instance of OnConflictClause, %s given',
                __METHOD__,
                is_object($onConflict) ? 'object(' . get_class($onConflict) . ')' : gettype($onConflict)
            ));
        }
        $this->setProperty($this->p_onConflict, $onConflict);
    }

    public function setOverriding(?string $overriding = null): void
    {
        if (null !== $overriding && !isset(self::ALLOWED_OVERRIDING[$overriding])) {
            throw new InvalidArgumentException("Unknown override kind '{$overriding}'");
        }
        $this->p_overriding = $overriding;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkInsertStatement($this);
    }
}
