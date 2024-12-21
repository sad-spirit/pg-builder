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

use sad_spirit\pg_builder\exceptions\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\GenericNode;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo AT TIME ZONE bar" expression
 *
 * @property ScalarExpression $argument
 * @property ScalarExpression $timeZone
 */
class AtTimeZoneExpression extends GenericNode implements ScalarExpression
{
    /** @var ScalarExpression */
    protected $p_argument;
    /** @var ScalarExpression */
    protected $p_timeZone;

    public function __construct(ScalarExpression $argument, ScalarExpression $timeZone)
    {
        if ($argument === $timeZone) {
            throw new InvalidArgumentException("Cannot use the same Node for argument and time zone");
        }

        $this->generatePropertyNames();

        $this->p_argument = $argument;
        $this->p_argument->setParentNode($this);

        $this->p_timeZone = $timeZone;
        $this->p_timeZone->setParentNode($this);
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setTimeZone(ScalarExpression $timeZone): void
    {
        $this->setRequiredProperty($this->p_timeZone, $timeZone);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkAtTimeZoneExpression($this);
    }

    public function getPrecedence(): int
    {
        return self::PRECEDENCE_TIME_ZONE;
    }

    public function getAssociativity(): string
    {
        return self::ASSOCIATIVE_LEFT;
    }
}
