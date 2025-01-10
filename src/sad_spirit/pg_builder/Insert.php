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
    OnConflictClause
};
use sad_spirit\pg_builder\enums\InsertOverriding;

/**
 * AST node representing INSERT statement
 *
 * @property-read InsertTarget          $relation
 * @property      SetTargetList         $cols
 * @property      SelectCommon|null     $values
 * @property      InsertOverriding|null $overriding
 * @property      OnConflictClause|null $onConflict
 * @property      TargetList            $returning
 */
class Insert extends Statement
{
    protected InsertTarget $p_relation;
    protected SetTargetList $p_cols;
    protected ?SelectCommon $p_values = null;
    protected ?InsertOverriding $p_overriding = null;
    protected ?OnConflictClause $p_onConflict = null;
    protected TargetList $p_returning;

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

    public function setValues(?SelectCommon $values = null): void
    {
        $this->setProperty($this->p_values, $values);
    }

    /**
     * Sets the Node representing 'ON CONFLICT' clause
     */
    public function setOnConflict(string|OnConflictClause|null $onConflict): void
    {
        if (is_string($onConflict)) {
            $onConflict = $this->getParserOrFail('ON CONFLICT clause')->parseOnConflict($onConflict);
        }
        $this->setProperty($this->p_onConflict, $onConflict);
    }

    public function setOverriding(?InsertOverriding $overriding): void
    {
        $this->p_overriding = $overriding;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkInsertStatement($this);
    }
}
