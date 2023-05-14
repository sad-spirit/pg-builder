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

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    NonRecursiveNode,
    ScalarExpression
};
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * Node for type cast used in the context of AexprConst, this can only take the form of "type.name 'a string value'"
 *
 * Currently, this Node is only used by CycleClause, where values for $markColumn are defined as AexprConstant in
 * the grammar.
 *
 * @property StringConstant $argument
 */
class ConstantTypecastExpression extends TypecastExpression
{
    use ExpressionAtom;
    use NonRecursiveNode;

    public function setArgument(ScalarExpression $argument): void
    {
        if (!$argument instanceof StringConstant || $argument::TYPE_CHARACTER !== $argument->type) {
            throw new InvalidArgumentException(sprintf(
                "%s only allows a generic string constant as an argument, %s given",
                __CLASS__,
                $argument instanceof StringConstant
                ? ($argument::TYPE_HEXADECIMAL === $argument->type ? 'hexadecimal' : 'binary') . ' string'
                : get_class($argument)
            ));
        }
        parent::setArgument($argument);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkConstantTypecastExpression($this);
    }
}
