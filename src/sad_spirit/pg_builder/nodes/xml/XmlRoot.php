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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\enums\XmlStandalone;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlroot() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property      ScalarExpression      $xml
 * @property      ScalarExpression|null $version
 * @property-read XmlStandalone|null    $standalone
 */
class XmlRoot extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected ScalarExpression $p_xml;
    protected ?ScalarExpression $p_version;

    public function __construct(
        ScalarExpression $xml,
        ?ScalarExpression $version = null,
        protected ?XmlStandalone $p_standalone = null
    ) {
        if ($version === $xml) {
            throw new InvalidArgumentException("Cannot use the same Node for xml and version arguments");
        }

        $this->generatePropertyNames();

        $this->p_xml = $xml;
        $this->p_xml->setParentNode($this);

        if (null !== $version) {
            $this->p_version = $version;
            $this->p_version->setParentNode($this);
        }
    }

    public function setXml(ScalarExpression $xml): void
    {
        $this->setRequiredProperty($this->p_xml, $xml);
    }

    public function setVersion(?ScalarExpression $version): void
    {
        $this->setProperty($this->p_version, $version);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkXmlRoot($this);
    }
}
