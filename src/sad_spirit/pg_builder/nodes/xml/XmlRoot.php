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
 * Represents xmlroot() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property      ScalarExpression $xml
 * @property      ScalarExpression $version
 * @property-read string|null      $standalone
 */
class XmlRoot extends Node implements ScalarExpression
{
    protected static $standaloneOptions = array(
        'yes'      => true,
        'no'       => true,
        'no value' => true
    );

    public function __construct(ScalarExpression $xml, ScalarExpression $version = null, $standalone = null)
    {
        if (null !== $standalone) {
            $standalone = (string)$standalone;
            if (!isset(self::$standaloneOptions[$standalone])) {
                throw new InvalidArgumentException("Unknown standalone option '{$standalone}'");
            }
        }
        $this->setNamedProperty('xml', $xml);
        $this->setNamedProperty('version', $version);
        $this->props['standalone'] = $standalone;
    }

    public function setXml(ScalarExpression $xml)
    {
        $this->setNamedProperty('xml', $xml);
    }

    public function setVersion(ScalarExpression $version = null)
    {
        $this->setNamedProperty('version', $version);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlRoot($this);
    }
}
