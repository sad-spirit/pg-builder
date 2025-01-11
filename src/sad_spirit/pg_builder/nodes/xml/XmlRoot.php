<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
