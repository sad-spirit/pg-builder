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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
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
