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

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\Identifier,
    sad_spirit\pg_builder\nodes\lists\TargetList,
    sad_spirit\pg_builder\nodes\lists\ExpressionList,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlelement() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read Identifier     $name
 * @property-read TargetList     $attributes
 * @property-read ExpressionList $content
 */
class XmlElement extends Node implements ScalarExpression
{
    public function __construct(Identifier $name, TargetList $attributes = null, ExpressionList $content = null)
    {
        $this->setNamedProperty('name', $name);
        $this->setNamedProperty('attributes', $attributes ?: new TargetList());
        $this->setNamedProperty('content', $content ?: new ExpressionList());
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlElement($this);
    }
}