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
 * @copyright 2014-2022 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing XMLEXISTS(...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.xmlexists as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property ScalarExpression $xpath
 * @property ScalarExpression $xml
 */
class XmlExists extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @var ScalarExpression */
    protected $p_xpath;
    /** @var ScalarExpression */
    protected $p_xml;

    public function __construct(ScalarExpression $xpath, ScalarExpression $xml)
    {
        if ($xpath === $xml) {
            throw new InvalidArgumentException("Cannot use the same Node for both arguments to XMLEXISTS()");
        }

        $this->generatePropertyNames();

        $this->p_xpath = $xpath;
        $this->p_xpath->setParentNode($this);

        $this->p_xml = $xml;
        $this->p_xml->setParentNode($this);
    }

    public function setXpath(ScalarExpression $xpath): void
    {
        $this->setRequiredProperty($this->p_xpath, $xpath);
    }

    public function setXml(ScalarExpression $xml): void
    {
        $this->setRequiredProperty($this->p_xml, $xml);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlExists($this);
    }
}
