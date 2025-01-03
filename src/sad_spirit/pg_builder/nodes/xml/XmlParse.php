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
use sad_spirit\pg_builder\enums\XmlOption;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents xmlparse() expression (cannot be a FunctionCall due to special arguments format)
 *
 * @property-read XmlOption        $documentOrContent
 * @property      ScalarExpression $argument
 * @property-read bool             $preserveWhitespace
 */
class XmlParse extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    protected XmlOption $p_documentOrContent;
    protected ScalarExpression $p_argument;
    protected bool $p_preserveWhitespace;

    public function __construct(
        XmlOption $documentOrContent,
        ScalarExpression $argument,
        bool $preserveWhitespace = false
    ) {
        $this->generatePropertyNames();

        $this->p_documentOrContent  = $documentOrContent;
        $this->p_preserveWhitespace = $preserveWhitespace;

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkXmlParse($this);
    }
}
