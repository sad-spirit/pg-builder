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

use sad_spirit\pg_builder\enums\IsJsonType;
use sad_spirit\pg_builder\nodes\ScalarExpression;
use sad_spirit\pg_builder\TreeWalker;

/**
 * AST node representing "foo ... IS [NOT] JSON ..." expression with all bells and whistles
 *
 * Cannot be represented by IsExpression due to aforementioned bells and whistles
 *
 * @property ScalarExpression $argument
 * @property ?IsJsonType      $type
 * @property ?bool            $uniqueKeys
 */
class IsJsonExpression extends NegatableExpression
{
    protected ScalarExpression $p_argument;
    protected ?IsJsonType $p_type;
    protected ?bool $p_uniqueKeys;

    public function __construct(
        ScalarExpression $argument,
        bool $not = false,
        ?IsJsonType $type = null,
        ?bool $unique = null
    ) {
        $this->generatePropertyNames();

        $this->setType($type);

        $this->p_argument   = $argument;
        $this->p_argument->setParentNode($this);
        $this->p_not        = $not;
        $this->p_uniqueKeys = $unique;
    }

    public function setArgument(ScalarExpression $argument): void
    {
        $this->setRequiredProperty($this->p_argument, $argument);
    }

    public function setType(?IsJsonType $type): void
    {
        $this->p_type = $type;
    }

    public function setUniqueKeys(?bool $unique): void
    {
        $this->p_uniqueKeys = $unique;
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkIsJsonExpression($this);
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
