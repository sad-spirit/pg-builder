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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\nodes\{
    ExpressionAtom,
    FunctionLike,
    GenericNode,
    ScalarExpression
};
use sad_spirit\pg_builder\enums\NormalizeForm;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing NORMALIZE(...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.normalize as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property      ScalarExpression $argument
 * @property-read ?NormalizeForm   $form
 */
class NormalizeExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    public function __construct(protected ScalarExpression $p_argument, protected ?NormalizeForm $p_form = null)
    {
        $this->generatePropertyNames();
        $this->p_argument->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkNormalizeExpression($this);
    }
}
