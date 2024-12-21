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

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an element of PASSING clause of various JSON expressions
 *
 * @property JsonFormattedValue  $value
 * @property Identifier $alias
 */
class JsonArgument extends GenericNode
{
    /** @var JsonFormattedValue */
    protected $p_value;
    /** @var Identifier */
    protected $p_alias;

    public function __construct(JsonFormattedValue $value, Identifier $alias)
    {
        $this->generatePropertyNames();

        $this->p_value = $value;
        $this->p_value->setParentNode($this);

        $this->p_alias = $alias;
        $this->p_alias->setParentNode($this);
    }

    public function setValue(JsonFormattedValue $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function setAlias(Identifier $alias): void
    {
        $this->setRequiredProperty($this->p_alias, $alias);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonArgument($this);
    }
}
