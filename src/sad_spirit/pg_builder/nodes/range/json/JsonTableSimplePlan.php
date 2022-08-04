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

use sad_spirit\pg_builder\nodes\{
    GenericNode,
    Identifier,
    NonRecursiveNode
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing a reference to a named path within "PLAN(...)" clause of json_table()
 *
 * @property Identifier $name
 */
class JsonTableSimplePlan extends GenericNode implements JsonTableSpecificPlan
{
    use NonRecursiveNode;

    /** @var Identifier */
    protected $p_name;

    public function __construct(Identifier $name)
    {
        $this->generatePropertyNames();

        $this->p_name = $name;
        $this->p_name->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonTableSimplePlan($this);
    }
}
