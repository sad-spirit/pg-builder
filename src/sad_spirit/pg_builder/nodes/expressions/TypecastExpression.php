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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    nodes\FunctionLike,
    nodes\GenericNode,
    nodes\TypeName,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * AST node representing a conversion of some value to a given datatype
 *
 * All possible type casting expressions are represented by this node:
 *  * CAST(foo as bar)
 *  * foo::bar
 *  * bar 'string constant'
 *
 * @property      ScalarExpression $argument
 * @property-read TypeName         $type
 */
class TypecastExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    /** @var ScalarExpression|null */
    protected $p_argument;
    /** @var TypeName */
    protected $p_type;

    public function __construct(ScalarExpression $argument, TypeName $type)
    {
        $this->generatePropertyNames();

        $this->setArgument($argument);

        $this->p_type = $type;
        $this->p_type->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        if (!empty($this->p_argument)) {
            $this->setRequiredProperty($this->p_argument, $argument);
        } else {
            $this->p_argument = $argument;
            $this->p_argument->setParentNode($this);
        }
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkTypecastExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_TYPECAST;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
