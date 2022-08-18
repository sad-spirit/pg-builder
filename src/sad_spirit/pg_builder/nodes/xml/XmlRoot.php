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
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlroot() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property      ScalarExpression      $xml
 * @property      ScalarExpression|null $version
 * @property-read string|null           $standalone
 */
class XmlRoot extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    public const YES      = 'yes';
    public const NO       = 'no';
    public const NO_VALUE = 'no value';

    private const STANDALONE_OPTIONS = [
        self::YES      => true,
        self::NO       => true,
        self::NO_VALUE => true
    ];

    /** @var ScalarExpression */
    protected $p_xml;
    /** @var ScalarExpression|null */
    protected $p_version;
    /** @var string|null */
    protected $p_standalone;

    public function __construct(ScalarExpression $xml, ScalarExpression $version = null, ?string $standalone = null)
    {
        if ($version === $xml) {
            throw new InvalidArgumentException("Cannot use the same Node for xml and version arguments");
        }
        if (null !== $standalone && !isset(self::STANDALONE_OPTIONS[$standalone])) {
            throw new InvalidArgumentException("Unknown standalone option '{$standalone}'");
        }

        $this->generatePropertyNames();

        $this->p_xml = $xml;
        $this->p_xml->setParentNode($this);

        if (null !== $version) {
            $this->p_version = $version;
            $this->p_version->setParentNode($this);
        }

        $this->p_standalone = $standalone;
    }

    public function setXml(ScalarExpression $xml): void
    {
        $this->setRequiredProperty($this->p_xml, $xml);
    }

    public function setVersion(ScalarExpression $version = null): void
    {
        $this->setProperty($this->p_version, $version);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlRoot($this);
    }
}
