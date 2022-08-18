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

namespace sad_spirit\pg_builder\nodes\expressions;

use sad_spirit\pg_builder\{
    TreeWalker,
    exceptions\InvalidArgumentException,
    nodes\ExpressionAtom,
    nodes\HasBothPropsAndOffsets,
    nodes\ScalarExpression
};
use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;

/**
 * Represents a CASE expression (with or without argument)
 *
 * @property ScalarExpression|null $argument
 * @property ScalarExpression|null $else
 * @extends NonAssociativeList<WhenExpression, iterable<WhenExpression>, WhenExpression>
 */
class CaseExpression extends NonAssociativeList implements ScalarExpression
{
    use ExpressionAtom;
    use HasBothPropsAndOffsets;

    /** @var ScalarExpression|null */
    protected $p_argument;
    /** @var ScalarExpression|null */
    protected $p_else;

    protected static function getAllowedElementClasses(): array
    {
        return [WhenExpression::class];
    }

    /**
     * CaseExpression constructor
     *
     * @param iterable<WhenExpression> $whenClauses
     * @param ScalarExpression|null    $elseClause
     * @param ScalarExpression|null    $argument
     */
    public function __construct(
        iterable $whenClauses,
        ScalarExpression $elseClause = null,
        ScalarExpression $argument = null
    ) {
        if (null !== $elseClause && $elseClause === $argument) {
            throw new InvalidArgumentException("Cannot use the same Node for CASE argument and ELSE clause");
        }

        $this->generatePropertyNames();
        parent::__construct($whenClauses);
        if (1 > count($this->offsets)) {
            throw new InvalidArgumentException(__CLASS__ . ': at least one WHEN clause is required');
        }

        if (null !== $argument) {
            $this->p_argument = $argument;
            $this->p_argument->setParentNode($this);
        }
        if (null !== $elseClause) {
            $this->p_else = $elseClause;
            $this->p_else->setParentNode($this);
        }
    }

    public function setArgument(ScalarExpression $argument = null): void
    {
        $this->setProperty($this->p_argument, $argument);
    }

    public function setElse(ScalarExpression $elseClause = null): void
    {
        $this->setProperty($this->p_else, $elseClause);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkCaseExpression($this);
    }
}
