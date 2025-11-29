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
    ScalarExpression
};
use sad_spirit\pg_builder\enums\XmlOption;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlparse() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read XmlOption        $documentOrContent
 * @property      ScalarExpression $argument
 * @property-read bool             $preserveWhitespace
 */
class XmlParse extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @internal Maps to `$documentOrContent` magic property, use the latter instead */
    protected XmlOption $p_documentOrContent;
    /** @internal Maps to `$argument` magic property, use the latter instead */
    protected ScalarExpression $p_argument;
    /** @internal Maps to `$preserveWhitespace` magic property, use the latter instead */
    protected bool $p_preserveWhitespace;

    public function __construct(
        XmlOption $documentOrContent,
        ScalarExpression $argument,
        bool $preserveWhitespace = false
    ) {
        $this->generatePropertyNames();

        $this->p_documentOrContent = $documentOrContent;

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_preserveWhitespace = $preserveWhitespace;
    }

    /** @internal Support method for `$argument` magic property, use the property instead */
    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlParse($this);
    }
}
