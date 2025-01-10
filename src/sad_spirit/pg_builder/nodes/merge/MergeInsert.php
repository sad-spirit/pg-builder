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

namespace sad_spirit\pg_builder\nodes\merge;

use sad_spirit\pg_builder\enums\InsertOverriding;
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\lists\SetTargetList;

/**
 * AST node representing INSERT action for MERGE statements
 *
 * @property SetTargetList         $cols
 * @property MergeValues|null      $values
 * @property InsertOverriding|null $overriding
 */
class MergeInsert extends GenericNode
{
    protected SetTargetList $p_cols;
    protected ?MergeValues $p_values = null;
    protected ?InsertOverriding $p_overriding = null;

    public function __construct(
        ?SetTargetList $cols = null,
        ?MergeValues $values = null,
        ?InsertOverriding $overriding = null
    ) {
        $this->generatePropertyNames();

        $this->p_cols = $cols ?? new SetTargetList();
        $this->p_cols->setParentNode($this);

        if (null !== $values) {
            $this->p_values = $values;
            $this->p_values->setParentNode($this);
        }

        $this->setOverriding($overriding);
    }

    public function setValues(?MergeValues $values): void
    {
        $this->setProperty($this->p_values, $values);
    }

    public function setOverriding(?InsertOverriding $overriding): void
    {
        $this->p_overriding = $overriding;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkMergeInsert($this);
    }
}
