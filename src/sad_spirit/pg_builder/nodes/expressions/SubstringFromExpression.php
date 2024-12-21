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
use sad_spirit\pg_builder\TreeWalker;
use sad_spirit\pg_builder\exceptions\InvalidArgumentException;

/**
 * AST node representing SUBSTRING(string FROM ...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.substring as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property      ScalarExpression      $string
 * @property      ScalarExpression|null $from
 * @property      ScalarExpression|null $for
 */
class SubstringFromExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @var ScalarExpression */
    protected $p_string;
    /** @var ScalarExpression|null */
    protected $p_from;
    /** @var ScalarExpression|null */
    protected $p_for;

    public function __construct(
        ScalarExpression $string,
        ScalarExpression $from = null,
        ScalarExpression $for = null
    ) {
        if (null === $from && null === $for) {
            throw new InvalidArgumentException("At least one of FROM and FOR arguments is required");
        }
        if ($string === $from || $from === $for || $string === $for) {
            throw new InvalidArgumentException("Cannot use the same Node for different arguments of SUBSTRING");
        }

        $this->generatePropertyNames();

        $this->p_string = $string;
        $this->p_string->setParentNode($this);

        if (null !== $from) {
            $this->p_from = $from;
            $this->p_from->setParentNode($this);
        }

        if (null !== $for) {
            $this->p_for = $for;
            $this->p_for->setParentNode($this);
        }
    }

    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    public function setFrom(?ScalarExpression $from): void
    {
        if (null === $from && null === $this->p_for) {
            throw new InvalidArgumentException("At least one of FROM and FOR arguments is required");
        }
        $this->setProperty($this->p_from, $from);
    }

    public function setFor(?ScalarExpression $for): void
    {
        if (null === $for && null === $this->p_from) {
            throw new InvalidArgumentException("At least one of FROM and FOR arguments is required");
        }
        $this->setProperty($this->p_for, $for);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkSubstringFromExpression($this);
    }
}
