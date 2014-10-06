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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\Node,
    sad_spirit\pg_builder\nodes\ScalarExpression,
    sad_spirit\pg_builder\exceptions\InvalidArgumentException,
    sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlparse() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read string           $documentOrContent
 * @property      ScalarExpression $argument
 * @property-read bool             $preserveWhitespace
 */
class XmlParse extends Node implements ScalarExpression
{
    public function __construct($documentOrContent, ScalarExpression $argument, $preserveWhitespace = false)
    {
        if (!in_array($documentOrContent, array('document', 'content'), true)) {
            throw new InvalidArgumentException(
                "Either 'document' or 'content' option required, '{$documentOrContent}' given"
            );
        }
        $this->props['documentOrContent']  = $documentOrContent;
        $this->props['preserveWhitespace'] = (bool)$preserveWhitespace;
        $this->setNamedProperty('argument', $argument);
    }

    public function setArgument(ScalarExpression $argument)
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlParse($this);
    }
}