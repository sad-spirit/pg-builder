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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\{
    exceptions\InvalidArgumentException,
    nodes\Identifier,
    nodes\ScalarExpression,
    nodes\TypeName,
    TreeWalker
};

/**
 * AST node for column definitions in XMLTABLE clause having a type name and possibly optional clauses
 *
 * @property TypeName              $type
 * @property ScalarExpression|null $path
 * @property bool|null             $nullable
 * @property ScalarExpression|null $default
 */
class XmlTypedColumnDefinition extends XmlColumnDefinition
{
    /** @var TypeName */
    protected $p_type;
    /** @var ScalarExpression|null */
    protected $p_path = null;
    /** @var bool|null */
    protected $p_nullable = null;
    /** @var ScalarExpression|null */
    protected $p_default = null;

    public function __construct(
        Identifier $name,
        TypeName $type,
        ScalarExpression $path = null,
        ?bool $nullable = null,
        ScalarExpression $default = null
    ) {
        if (null !== $path && $path === $default) {
            throw new InvalidArgumentException("Cannot use the same Node for path and default arguments");
        }

        parent::__construct($name);

        $this->p_type = $type;
        $this->p_type->setParentNode($this);

        if (null !== $path) {
            $this->p_path = $path;
            $this->p_path->setParentNode($this);
        }

        if (null !== $default) {
            $this->p_default = $default;
            $this->p_default->setParentNode($this);
        }

        $this->p_nullable = $nullable;
    }

    public function setType(TypeName $type): void
    {
        $this->setRequiredProperty($this->p_type, $type);
    }

    public function setPath(ScalarExpression $path = null): void
    {
        $this->setProperty($this->p_path, $path);
    }

    public function setNullable(?bool $nullable = null): void
    {
        $this->p_nullable = $nullable;
    }

    public function setDefault(ScalarExpression $default = null): void
    {
        $this->setProperty($this->p_default, $default);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlTypedColumnDefinition($this);
    }
}
