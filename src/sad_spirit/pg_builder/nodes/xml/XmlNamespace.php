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
    nodes\GenericNode,
    nodes\ScalarExpression,
    nodes\Identifier,
    TreeWalker
};

/**
 * AST node representing an XML namespace in XMLTABLE clause
 *
 * @property ScalarExpression $value
 * @property Identifier|null  $alias
 */
class XmlNamespace extends GenericNode
{
    protected ?Identifier $p_alias = null;

    public function __construct(protected ScalarExpression $p_value, ?Identifier $alias = null)
    {
        $this->generatePropertyNames();
        $this->p_value->setParentNode($this);

        if (null !== $alias) {
            $this->p_alias = $alias;
            $this->p_alias->setParentNode($this);
        }
    }

    public function setValue(ScalarExpression $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function setAlias(?Identifier $alias): void
    {
        $this->setProperty($this->p_alias, $alias);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlNamespace($this);
    }
}
