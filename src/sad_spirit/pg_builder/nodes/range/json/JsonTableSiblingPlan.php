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

namespace sad_spirit\pg_builder\nodes\range\json;

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing part of "PLAN(...)" clause for json_table() that defines sibling joining plan
 *
 * @property JsonTableSpecificPlan $left
 * @property JsonTableSpecificPlan $right
 * @property string                $type
 */
class JsonTableSiblingPlan extends GenericNode implements JsonTableSpecificPlan
{
    /** @var JsonTableSpecificPlan */
    protected $p_left;
    /** @var JsonTableSpecificPlan */
    protected $p_right;
    /** @var string */
    protected $p_type;

    public function __construct(JsonTableSpecificPlan $left, JsonTableSpecificPlan $right, string $type)
    {
        if ($left === $right) {
            throw new InvalidArgumentException("Cannot use the same Node for both sides of joining plan");
        }

        $this->generatePropertyNames();

        $this->p_left = $left;
        $this->p_left->setParentNode($this);

        $this->p_right = $right;
        $this->p_right->setParentNode($this);

        $this->setType($type);
    }

    public function setLeft(JsonTableSpecificPlan $left): void
    {
        $this->setRequiredProperty($this->p_left, $left);
    }

    public function setRight(JsonTableSpecificPlan $right): void
    {
        $this->setRequiredProperty($this->p_right, $right);
    }

    public function setType(string $type): void
    {
        if (!in_array($type, JsonTablePlan::SIBLING)) {
            throw new InvalidArgumentException(sprintf(
                "Unrecognized value '%s' for sibling joining plan, expected one of '%s'",
                $type,
                implode("', '", JsonTablePlan::SIBLING)
            ));
        }
        $this->p_type = $type;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonTableSiblingPlan($this);
    }
}
