<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\expressions\ConstantTypecastExpression;
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
    use NonRecursiveNode {
        setParentNode as private setParentNodeImpl;
    }

    protected bool $p_setOf = false;
    /** @var array<int,int> */
    protected array $p_bounds = [];
    protected ?TypeModifierList $p_modifiers = null;

    public function __construct(protected QualifiedName $p_name, ?TypeModifierList $typeModifiers = null)
    {
        $this->generatePropertyNames();
        $this->p_name->setParentNode($this);

        $this->p_modifiers = $typeModifiers ?? new TypeModifierList();
        $this->p_modifiers->setParentNode($this);
    }

    public function setSetOf(bool $setOf): void
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
            if (!\is_int($value) && !\ctype_digit($value)) {
                throw new InvalidArgumentException(\sprintf(
                    "%s: array bounds should be an array of integers, %s given at key '%s'",
                    __METHOD__,
                    \gettype($value),
                    $key
                ));
            }
            $this->p_bounds[] = (int)$value;
        }
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkTypeName($this);
    }

    public function setParentNode(?Node $parent): void
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
