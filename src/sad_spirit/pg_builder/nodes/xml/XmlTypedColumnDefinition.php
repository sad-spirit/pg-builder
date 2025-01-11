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

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    nodes\Identifier,
    nodes\ScalarExpression,
    nodes\TypeName,
    TreeWalker
};

/**
 * AST node for column definitions in XMLTABLE clause having a type name and possibly optional clauses
 *
 * @property TypeName              $type
 * @property ScalarExpression|null $path
 * @property bool|null             $nullable
 * @property ScalarExpression|null $default
 */
class XmlTypedColumnDefinition extends XmlColumnDefinition
{
    protected ?ScalarExpression $p_path = null;
    protected ?ScalarExpression $p_default = null;

    public function __construct(
        Identifier $name,
        protected TypeName $p_type,
        ?ScalarExpression $path = null,
        protected ?bool $p_nullable = null,
        ?ScalarExpression $default = null
    ) {
        if (null !== $path && $path === $default) {
            throw new InvalidArgumentException("Cannot use the same Node for path and default arguments");
        }

        parent::__construct($name);
        $this->p_type->setParentNode($this);

        if (null !== $path) {
            $this->p_path = $path;
            $this->p_path->setParentNode($this);
        }

        if (null !== $default) {
            $this->p_default = $default;
            $this->p_default->setParentNode($this);
        }
    }

    public function setType(TypeName $type): void
    {
        $this->setRequiredProperty($this->p_type, $type);
    }

    public function setPath(?ScalarExpression $path): void
    {
        $this->setProperty($this->p_path, $path);
    }

    public function setNullable(?bool $nullable): void
    {
        $this->p_nullable = $nullable;
    }

    public function setDefault(?ScalarExpression $default): void
    {
        $this->setProperty($this->p_default, $default);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlTypedColumnDefinition($this);
    }
}
