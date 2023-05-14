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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\expressions\Constant;
use sad_spirit\pg_builder\nodes\expressions\ConstantTypecastExpression;
use sad_spirit\pg_builder\nodes\lists\TypeModifierList;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents a type name with all bells and whistles
 *
 * @psalm-property-read TypeModifierList $modifiers
 *
 * @property      bool                                     $setOf
 * @property      array                                    $bounds
 * @property-read QualifiedName                            $name
 * @property-read TypeModifierList|Constant[]|Identifier[] $modifiers
 */
class TypeName extends GenericNode
{
    use NonRecursiveNode {
        setParentNode as private setParentNodeImpl;
    }

    /** @var bool */
    protected $p_setOf = false;
    /** @var array<int,int> */
    protected $p_bounds = [];
    /** @var QualifiedName */
    protected $p_name;
    /** @var TypeModifierList */
    protected $p_modifiers;

    public function __construct(QualifiedName $typeName, TypeModifierList $typeModifiers = null)
    {
        $this->generatePropertyNames();

        $this->p_name = $typeName;
        $this->p_name->setParentNode($this);

        $this->p_modifiers = $typeModifiers ?? new TypeModifierList();
        $this->p_modifiers->setParentNode($this);
    }

    public function setSetOf(bool $setOf = false): void
    {
        if ($setOf && $this->parentNode instanceof ConstantTypecastExpression) {
            throw new InvalidArgumentException('Type names with SETOF cannot be used in constant type cast');
        }
        $this->p_setOf = $setOf;
    }

    /**
     * Sets the bounds for an array type
     *
     * @param array<int, int|string> $bounds
     */
    public function setBounds(array $bounds): void
    {
        if ([] !== $bounds && $this->parentNode instanceof ConstantTypecastExpression) {
            throw new InvalidArgumentException('Type names with array bounds cannot be used in constant type cast');
        }
        $this->p_bounds = [];
        foreach ($bounds as $key => $value) {
            if (!is_int($value) && !ctype_digit($value)) {
                throw new InvalidArgumentException(sprintf(
                    "%s: array bounds should be an array of integers, %s given at key '%s'",
                    __METHOD__,
                    gettype($value),
                    $key
                ));
            }
            $this->p_bounds[] = (int)$value;
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkTypeName($this);
    }

    public function setParentNode(Node $parent = null): void
    {
        if (
            $parent instanceof ConstantTypecastExpression
            && (
                false !== $this->p_setOf
                || [] !== $this->p_bounds
            )
        ) {
            throw new InvalidArgumentException(
                'Type names with array bounds or SETOF cannot be used in constant type cast'
            );
        }
        $this->setParentNodeImpl($parent);
    }
}
