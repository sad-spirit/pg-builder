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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes;

use sad_spirit\pg_builder\nodes\lists\NonAssociativeList;
use sad_spirit\pg_builder\TreeWalker;

/**
 * Represents an indirection (field selections or array subscripts) applied to an expression
 *
 * @property ScalarExpression $expression
 * @extends NonAssociativeList<
 *     Identifier|ArrayIndexes|Star,
 *     iterable<Identifier|ArrayIndexes|Star>,
 *     Identifier|ArrayIndexes|Star
 * >
 */
class Indirection extends NonAssociativeList implements ScalarExpression
{
    use HasBothPropsAndOffsets;

    /** @var ScalarExpression */
    protected $p_expression;

    protected static function getAllowedElementClasses(): array
    {
        return [
            Identifier::class,
            ArrayIndexes::class,
            Star::class
        ];
    }

    /**
     * Indirection constructor
     *
     * @param iterable<Identifier|ArrayIndexes|Star> $indirection
     * @param ScalarExpression                       $expression
     */
    public function __construct($indirection, ScalarExpression $expression)
    {
        $this->generatePropertyNames();
        parent::__construct($indirection);

        $this->p_expression = $expression;
        $this->p_expression->setParentNode($this);
    }

    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIndirection($this);
    }

    public function getPrecedence(): int
    {
        // actual precedence depends on contents of $nodes
        return self::PRECEDENCE_ATOM;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
