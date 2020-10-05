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
    TreeWalker
};

/**
 * Represents xmlpi() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read Identifier            $name
 * @property      ScalarExpression|null $content
 */
class XmlPi extends GenericNode implements ScalarExpression
{
    public function __construct(Identifier $name, ScalarExpression $content = null)
    {
        $this->setNamedProperty('name', $name);
        $this->setNamedProperty('content', $content);
    }

    public function setContent(ScalarExpression $content): void
    {
        $this->setNamedProperty('content', $content);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlPi($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_ATOM;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
