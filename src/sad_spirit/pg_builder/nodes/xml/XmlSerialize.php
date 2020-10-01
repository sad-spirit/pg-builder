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

namespace sad_spirit\pg_builder\nodes\xml;

use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\TypeName;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlserialize() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read string           $documentOrContent
 * @property      ScalarExpression $argument
 * @property-read TypeName         $type
 */
class XmlSerialize extends GenericNode implements ScalarExpression
{
    public function __construct($documentOrContent, ScalarExpression $argument, TypeName $typeName)
    {
        if (!in_array($documentOrContent, ['document', 'content'], true)) {
            throw new InvalidArgumentException(
                "Either 'document' or 'content' option required, '{$documentOrContent}' given"
            );
        }
        $this->props['documentOrContent'] = $documentOrContent;
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('type', $typeName);
    }

    public function setArgument(ScalarExpression $argument)
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlSerialize($this);
    }
}
