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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    ScalarExpression,
    GenericNode,
    Identifier,
    lists\TargetList,
    lists\ExpressionList
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlelement() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read Identifier     $name
 * @property-read TargetList     $attributes
 * @property      ExpressionList $content
 */
class XmlElement extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected TargetList $p_attributes;
    protected ExpressionList $p_content;

    public function __construct(
        protected Identifier $p_name,
        ?TargetList $attributes = null,
        ?ExpressionList $content = null
    ) {
        $this->generatePropertyNames();
        $this->p_name->setParentNode($this);

        $this->p_attributes = $attributes ?? new TargetList();
        $this->p_attributes->setParentNode($this);

        $this->p_content = $content ?? new ExpressionList();
        $this->p_content->setParentNode($this);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlElement($this);
    }
}
