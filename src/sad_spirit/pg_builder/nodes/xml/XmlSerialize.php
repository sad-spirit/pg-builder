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
    TreeWalker,
    exceptions\InvalidArgumentException,
    nodes\ExpressionAtom,
    nodes\GenericNode,
    nodes\TypeName,
    nodes\ScalarExpression
};

/**
 * Represents xmlserialize() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read string           $documentOrContent
 * @property      ScalarExpression $argument
 * @property-read TypeName         $type
 */
class XmlSerialize extends GenericNode implements ScalarExpression
{
    use ExpressionAtom;

    public const DOCUMENT = 'document';
    public const CONTENT  = 'content';

    private const ALLOWED_TYPES = [
        self::DOCUMENT => true,
        self::CONTENT  => true
    ];

    public function __construct(string $documentOrContent, ScalarExpression $argument, TypeName $typeName)
    {
        if (!isset(self::ALLOWED_TYPES[$documentOrContent])) {
            throw new InvalidArgumentException(
                "Either 'document' or 'content' option required, '{$documentOrContent}' given"
            );
        }
        $this->props['documentOrContent'] = $documentOrContent;
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('type', $typeName);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlSerialize($this);
    }
}
