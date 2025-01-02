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

use sad_spirit\pg_builder\enums\IsPredicate;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo IS [NOT] keyword" expression
 *
 * Allowed keywords are TRUE / FALSE / NULL / UNKNOWN / DOCUMENT / [NFC|NFD|NFKC|NFKD] NORMALIZED
 *
 * @property ScalarExpression $argument
 * @property IsPredicate      $what
 */
class IsExpression extends NegatableExpression
{
    protected ScalarExpression $p_argument;
    protected IsPredicate $p_what;

    public function __construct(ScalarExpression $argument, IsPredicate $what, bool $not = false)
    {
        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_not = $not;

        $this->setWhat($what);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setWhat(IsPredicate $what): void
    {
        $this->p_what = $what;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_IS;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_NONE;
    }
}
