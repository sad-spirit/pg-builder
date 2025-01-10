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

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\ScalarExpression,
    nodes\Identifier,
    TreeWalker
};

/**
 * AST node representing an XML namespace in XMLTABLE clause
 *
 * @property ScalarExpression $value
 * @property Identifier|null  $alias
 */
class XmlNamespace extends GenericNode
{
    protected ?Identifier $p_alias = null;

    public function __construct(protected ScalarExpression $p_value, ?Identifier $alias = null)
    {
        $this->generatePropertyNames();
        $this->p_value->setParentNode($this);

        if (null !== $alias) {
            $this->p_alias = $alias;
            $this->p_alias->setParentNode($this);
        }
    }

    public function setValue(ScalarExpression $value): void
    {
        $this->setRequiredProperty($this->p_value, $value);
    }

    public function setAlias(?Identifier $alias): void
    {
        $this->setProperty($this->p_alias, $alias);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlNamespace($this);
    }
}
