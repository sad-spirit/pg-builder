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
    GenericNode,
    ScalarExpression,
    TypeName
};
use sad_spirit\pg_builder\enums\XmlOption;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlserialize() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read XmlOption        $documentOrContent
 * @property      ScalarExpression $argument
 * @property-read TypeName         $type
 * @property      bool|null        $indent
 */
class XmlSerialize extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @internal Maps to `$documentOrContent` magic property, use the latter instead */
    protected XmlOption $p_documentOrContent;
    /** @internal Maps to `$argument` magic property, use the latter instead */
    protected ScalarExpression $p_argument;
    /** @internal Maps to `$type` magic property, use the latter instead */
    protected TypeName $p_type;
    /** @internal Maps to `$indent` magic property, use the latter instead */
    protected ?bool $p_indent;

    public function __construct(
        XmlOption $documentOrContent,
        ScalarExpression $argument,
        TypeName $type,
        ?bool $indent = null
    ) {
        $this->generatePropertyNames();

        $this->p_documentOrContent = $documentOrContent;
        $this->p_argument          = $argument;
        $this->p_type              = $type;
        $this->p_indent            = $indent;

        $this->p_argument->setParentNode($this);
        $this->p_type->setParentNode($this);
    }

    /** @internal Support method for `$argument` magic property, use the property instead */
    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    /** @internal Support method for `$indent` magic property, use the property instead */
    public function setIndent(?bool $indent): void
    {
        $this->p_indent = $indent;
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlSerialize($this);
    }
}
