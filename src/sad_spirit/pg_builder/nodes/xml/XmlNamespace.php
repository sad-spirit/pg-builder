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

use sad_spirit\pg_builder\Node;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing an XML namespace in XMLTABLE clause
 *
 * @property ScalarExpression $value
 * @property Identifier|null  $alias
 */
class XmlNamespace extends Node
{
    public function __construct(ScalarExpression $value, Identifier $alias = null)
    {
        $this->setValue($value);
        $this->setAlias($alias);
    }

    public function setValue(ScalarExpression $value)
    {
        $this->setNamedProperty('value', $value);
    }

    public function setAlias(Identifier $alias = null)
    {
        $this->setNamedProperty('alias', $alias);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlNamespace($this);
    }
}
