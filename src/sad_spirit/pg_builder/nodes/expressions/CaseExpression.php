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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    nodes\ScalarExpression,
    exceptions\InvalidArgumentException,
    TreeWalker
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents a CASE expression (with or without argument)
 *
 * @property ScalarExpression|null $argument
 * @property ScalarExpression|null $else
 */
class CaseExpression extends NonAssociativeList implements ScalarExpression
{
    protected static function getAllowedElementClasses(): array
    {
        return [WhenExpression::class];
    }

    public function __construct($whenClauses, ScalarExpression $elseClause = null, ScalarExpression $argument = null)
    {
        parent::__construct($whenClauses);
        if (1 > count($this->nodes)) {
            throw new InvalidArgumentException(__CLASS__ . ': at least one WHEN clause is required');
        }
        $this->setNamedProperty('argument', $argument);
        $this->setNamedProperty('else', $elseClause);
    }

    public function setArgument(ScalarExpression $argument = null): void
    {
        $this->setNamedProperty('argument', $argument);
    }

    public function setElse(ScalarExpression $elseClause = null): void
    {
        $this->setNamedProperty('else', $elseClause);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCaseExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_ATOM;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
