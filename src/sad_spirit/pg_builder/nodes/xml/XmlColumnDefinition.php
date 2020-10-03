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

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\Identifier,
    nodes\ScalarExpression,
    nodes\TypeName,
    TreeWalker
};

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
class XmlColumnDefinition extends GenericNode
{
    public function __construct(
        Identifier $name,
        bool $forOrdinality = false,
        TypeName $type = null,
        ScalarExpression $path = null,
        ?bool $nullable = null,
        ScalarExpression $default = null
    ) {
        $this->setName($name);
        $this->setForOrdinality($forOrdinality);
        $this->setType($type);
        $this->setPath($path);
        $this->setNullable($nullable);
        $this->setDefault($default);
    }

    public function setName(Identifier $name): void
    {
        $this->setNamedProperty('name', $name);
    }

    public function setForOrdinality(bool $forOrdinality = false): void
    {
        $this->props['forOrdinality'] = $forOrdinality;
    }

    public function setType(TypeName $type = null): void
    {
        $this->setNamedProperty('type', $type);
    }

    public function setPath(ScalarExpression $path = null): void
    {
        $this->setNamedProperty('path', $path);
    }

    public function setNullable(?bool $nullable = null): void
    {
        $this->props['nullable'] = $nullable;
    }

    public function setDefault(ScalarExpression $default = null): void
    {
        $this->setNamedProperty('default', $default);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlColumnDefinition($this);
    }
}
