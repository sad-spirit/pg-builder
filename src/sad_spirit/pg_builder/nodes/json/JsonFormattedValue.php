<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder\nodes\json;

use sad_spirit\pg_builder\{
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * Represents an expression with possible JSON format applied
 *
 * @property ScalarExpression $expression
 * @property JsonFormat|null  $format
 */
class JsonFormattedValue extends GenericNode
{
    protected JsonFormat|null $p_format = null;

    public function __construct(protected ScalarExpression $p_expression, ?JsonFormat $format = null)
    {
        $this->generatePropertyNames();
        $this->p_expression->setParentNode($this);

        if (null !== $format) {
            $this->p_format = $format;
            $this->p_format->setParentNode($this);
        }
    }

    public function setExpression(ScalarExpression $expression): void
    {
        $this->setRequiredProperty($this->p_expression, $expression);
    }

    public function setFormat(?JsonFormat $format): void
    {
        $this->setProperty($this->p_format, $format);
    }

    public function dispatch(TreeWalker $walker): mixed
    {
        return $walker->walkJsonFormattedValue($this);
    }
}
