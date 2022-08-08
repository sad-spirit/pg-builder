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

namespace sad_spirit\pg_builder\nodes\merge;

use sad_spirit\pg_builder\{
    Insert,
    TreeWalker
};
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\{
    GenericNode,
    SetTargetElement,
    lists\SetTargetList
};

/**
 * AST node representing INSERT action for MERGE statements
 *
 * @psalm-property SetTargetList $cols
 *
 * @property SetTargetList|SetTargetElement[] $cols
 * @property MergeValues|null                 $values
 * @property string|null                      $overriding
 */
class MergeInsert extends GenericNode
{
    /** @var SetTargetList */
    protected $p_cols;
    /** @var MergeValues|null */
    protected $p_values = null;
    /** @var string|null */
    protected $p_overriding;

    public function __construct(?SetTargetList $cols = null, ?MergeValues $values = null, ?string $overriding = null)
    {
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

    public function setOverriding(?string $overriding = null): void
    {
        if (null !== $overriding && !isset(Insert::ALLOWED_OVERRIDING[$overriding])) {
            throw new InvalidArgumentException("Unknown override kind '{$overriding}'");
        }
        $this->p_overriding = $overriding;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkMergeInsert($this);
    }
}
