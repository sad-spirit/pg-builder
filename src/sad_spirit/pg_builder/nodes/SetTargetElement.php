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

use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a target column (with possible indirection) for INSERT or UPDATE statements
 *
 * Indirection is represented by array offsets. Unlike normal Indirection nodes,
 * Star indirection is not possible as Postgres does not allow it:
 * 'ERROR:  row expansion via "*" is not supported here'
 *
 * @property Identifier $name
 * @extends NonAssociativeList<Identifier|ArrayIndexes>
 */
class SetTargetElement extends NonAssociativeList
{
    use NonRecursiveNode;
    use HasBothPropsAndOffsets;

    /** @var Identifier */
    protected $p_name;

    protected static function getAllowedElementClasses(): array
    {
        return [
            Identifier::class,
            ArrayIndexes::class
        ];
    }

    /**
     * SetTargetElement constructor
     *
     * @param string|Identifier                  $name
     * @param array<int,Identifier|ArrayIndexes> $indirection
     */
    public function __construct($name, array $indirection = [])
    {
        $this->generatePropertyNames();
        parent::__construct($indirection);
        $this->setName($name);
    }

    /**
     * Sets the target column name
     *
     * @param string|Identifier $name
     */
    public function setName($name): void
    {
        if (!($name instanceof Identifier)) {
            $name = new Identifier($name);
        }
        $this->setProperty($this->p_name, $name);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSetTargetElement($this);
    }
}
