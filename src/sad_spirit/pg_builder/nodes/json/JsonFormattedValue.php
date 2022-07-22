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
    /** @var ScalarExpression */
    protected $p_expression;
    /** @var JsonFormat|null */
    protected $p_format = null;

    public function __construct(ScalarExpression $expression, JsonFormat $format = null)
    {
        $this->generatePropertyNames();

        $this->p_expression = $expression;
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

    public function setFormat(JsonFormat $format): void
    {
        $this->setProperty($this->p_format, $format);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkJsonFormattedValue($this);
    }
}
