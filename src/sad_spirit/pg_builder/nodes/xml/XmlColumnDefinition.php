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
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\nodes\TypeName;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node for column definition in XMLTABLE clause
 *
 * @property Identifier            $name
 * @property TypeName|null         $type
 * @property bool                  $forOrdinality
 * @property ScalarExpression|null $path
 * @property bool|null             $nullable
 * @property ScalarExpression|null $default
 */
class XmlColumnDefinition extends Node
{
    public function __construct(
        Identifier $name,
        $forOrdinality = false,
        TypeName $type = null,
        ScalarExpression $path = null,
        $nullable = null,
        ScalarExpression $default = null
    ) {
        $this->setName($name);
        $this->setForOrdinality($forOrdinality);
        $this->setType($type);
        $this->setPath($path);
        $this->setNullable($nullable);
        $this->setDefault($default);
    }

    public function setName(Identifier $name)
    {
        $this->setNamedProperty('name', $name);
    }

    public function setForOrdinality($forOrdinality = false)
    {
        $this->props['forOrdinality'] = (bool)$forOrdinality;
    }

    public function setType(TypeName $type = null)
    {
        $this->setNamedProperty('type', $type);
    }

    public function setPath(ScalarExpression $path = null)
    {
        $this->setNamedProperty('path', $path);
    }

    public function setNullable($nullable = null)
    {
        $this->props['nullable'] = is_null($nullable) ? null : (bool)$nullable;
    }

    public function setDefault($default = null)
    {
        $this->setNamedProperty('default', $default);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlColumnDefinition($this);
    }
}
