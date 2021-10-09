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
 * AST node representing OVERLAY(...) function call with special arguments format
 *
 * Previously this was parsed to a FunctionExpression node having pg_catalog.overlay as function name.
 * As Postgres itself now outputs the original SQL standard form of the expression when generating SQL,
 * we follow the suit by creating a separate Node with SQL standard output.
 *
 * @property ScalarExpression      $string
 * @property ScalarExpression      $newSubstring
 * @property ScalarExpression      $start
 * @property ScalarExpression|null $count
 */
class OverlayExpression extends GenericNode implements ScalarExpression, FunctionLike
{
    use ExpressionAtom;

    /** @var ScalarExpression */
    protected $p_string;
    /** @var ScalarExpression */
    protected $p_newSubstring;
    /** @var ScalarExpression */
    protected $p_start;
    /** @var ScalarExpression|null */
    protected $p_count;

    public function __construct(
        ScalarExpression $string,
        ScalarExpression $newSubstring,
        ScalarExpression $start,
        ScalarExpression $count = null
    ) {
        $this->generatePropertyNames();

        $check = [];
        foreach ([$string, $newSubstring, $start, $count] as $object) {
            if (null === $object) {
                continue;
            }
            if (isset($check[$id = spl_object_id($object)])) {
                throw new InvalidArgumentException("Cannot use the same Node for different OVERLAY arguments");
            }
            $check[$id] = true;
        }

        $this->p_string = $string;
        $this->p_string->setParentNode($this);

        $this->p_newSubstring = $newSubstring;
        $this->p_newSubstring->setParentNode($this);

        $this->p_start = $start;
        $this->p_start->setParentNode($this);

        if (null !== $count) {
            $this->p_count = $count;
            $this->p_count->setParentNode($this);
        }
    }

    public function setString(ScalarExpression $string): void
    {
        $this->setRequiredProperty($this->p_string, $string);
    }

    public function setNewSubstring(ScalarExpression $newSubstring): void
    {
        $this->setRequiredProperty($this->p_newSubstring, $newSubstring);
    }

    public function setStart(ScalarExpression $start): void
    {
        $this->setRequiredProperty($this->p_start, $start);
    }

    public function setCount(?ScalarExpression $count): void
    {
        $this->setProperty($this->p_count, $count);
    }

    public function dispatch(TreeWalker $walker)
    {
        return $walker->walkOverlayExpression($this);
    }
}
