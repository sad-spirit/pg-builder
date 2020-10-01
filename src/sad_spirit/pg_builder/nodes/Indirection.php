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
 * Represents an indirection (field selections or array subscripts) applied to an expression
 *
 * @property ScalarExpression $expression
 */
class Indirection extends NonAssociativeList implements ScalarExpression
{
    protected static function getAllowedElementClasses(): array
    {
        return [
            Identifier::class,
            ArrayIndexes::class,
            Star::class
        ];
    }

    public function __construct($indirection, ScalarExpression $expression)
    {
        parent::__construct($indirection);
        $this->setNamedProperty('expression', $expression);
    }

    public function setExpression(ScalarExpression $expression)
    {
        $this->setNamedProperty('expression', $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIndirection($this);
    }
}
