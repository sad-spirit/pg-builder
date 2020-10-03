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

use sad_spirit\pg_builder\nodes\lists\TypeModifierList;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a type name with all bells and whistles
 *
 * @property      bool             $setOf
 * @property      array            $bounds
 * @property-read QualifiedName    $name
 * @property-read TypeModifierList $modifiers
 */
class TypeName extends GenericNode
{
    use LeafNode;

    public function __construct(QualifiedName $typeName, TypeModifierList $typeModifiers = null)
    {
        $this->props['setOf']     = false;
        $this->props['bounds']    = [];
        $this->setNamedProperty('name', $typeName);
        $this->setNamedProperty('modifiers', $typeModifiers ?? new TypeModifierList());
    }

    public function setSetOf(bool $setOf = false): void
    {
        $this->props['setOf'] = $setOf;
    }

    public function setBounds(array $bounds): void
    {
        $this->props['bounds'] = [];
        foreach ($bounds as $key => $value) {
            if (!is_int($value) && !ctype_digit($value)) {
                throw new InvalidArgumentException(sprintf(
                    "%s: array bounds should be an array of integers, %s given at key '%s'",
                    __METHOD__,
                    gettype($value),
                    $key
                ));
            }
            $this->props['bounds'][] = (int)$value;
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkTypeName($this);
    }
}
