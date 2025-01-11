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
    Identifier,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlpi() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read Identifier            $name
 * @property      ScalarExpression|null $content
 */
class XmlPi extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected ?ScalarExpression $p_content = null;

    public function __construct(protected Identifier $p_name, ?ScalarExpression $content = null)
    {
        $this->generatePropertyNames();
        $this->p_name->setParentNode($this);

        if (null !== $content) {
            $this->p_content = $content;
            $this->p_content->setParentNode($this);
        }
    }

    public function setContent(?ScalarExpression $content): void
    {
        $this->setProperty($this->p_content, $content);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlPi($this);
    }
}
