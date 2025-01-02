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

use sad_spirit\pg_builder\{
    SelectCommon,
    TreeWalker,
    enums\SubselectConstruct,
    nodes\ExpressionAtom,
    nodes\GenericNode,
    nodes\ScalarExpression
};

/**
 * AST node representing a subquery appearing in scalar expressions, possibly with a subquery operator applied
 *
 * @property      SelectCommon            $query
 * @property-read SubselectConstruct|null $operator
 */
class SubselectExpression extends GenericNode implements ScalarExpression
{
    use ExpressionAtom;

    protected SelectCommon $p_query;
    protected ?SubselectConstruct $p_operator;

    public function __construct(SelectCommon $query, ?SubselectConstruct $operator = null)
    {
        $this->generatePropertyNames();
        $this->p_query = $query;
        $this->p_query->setParentNode($this);
        $this->p_operator = $operator;
    }

    public function setQuery(SelectCommon $query): void
    {
        $this->setRequiredProperty($this->p_query, $query);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSubselectExpression($this);
    }
}
