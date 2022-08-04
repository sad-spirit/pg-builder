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
use sad_spirit\pg_builder\nodes\NonRecursiveNode;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "PLAN DEFAULT (...)" clause in json_table()
 *
 * @property string|null $parentChild
 * @property string|null $sibling
 */
class JsonTableDefaultPlan extends GenericNode implements JsonTablePlan
{
    use NonRecursiveNode;

    /** @var string|null */
    protected $p_parentChild = null;
    /** @var string|null */
    protected $p_sibling = null;

    public function __construct(?string $parentChild, ?string $sibling)
    {
        if (null === $parentChild && null === $sibling) {
            throw new InvalidArgumentException("At least one joining plan should be specified");
        }

        $this->generatePropertyNames();
        if (null !== $parentChild) {
            $this->setParentChild($parentChild);
        }
        if (null !== $sibling) {
            $this->setSibling($sibling);
        }
    }

    public function setParentChild(?string $parentChild): void
    {
        if (null === $parentChild && null === $this->p_sibling) {
            throw new InvalidArgumentException("At least one joining plan should be specified");
        }
        if (null !== $parentChild && !in_array($parentChild, JsonTablePlan::PARENT_CHILD)) {
            throw new InvalidArgumentException(sprintf(
                "Unrecognized value '%s' for parent-child joining plan, expected one of '%s'",
                $parentChild,
                implode("', '", JsonTablePlan::PARENT_CHILD)
            ));
        }
        $this->p_parentChild = $parentChild;
    }

    public function setSibling(?string $sibling): void
    {
        if (null === $sibling && null === $this->p_parentChild) {
            throw new InvalidArgumentException("At least one joining plan should be specified");
        }
        if (null !== $sibling && !in_array($sibling, JsonTablePlan::SIBLING)) {
            throw new InvalidArgumentException(sprintf(
                "Unrecognized value '%s' for sibling joining plan, expected one of '%s'",
                $sibling,
                implode("', '", JsonTablePlan::SIBLING)
            ));
        }
        $this->p_sibling = $sibling;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonTableDefaultPlan($this);
    }
}
