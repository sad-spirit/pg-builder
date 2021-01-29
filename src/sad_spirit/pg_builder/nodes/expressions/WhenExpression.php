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
    exceptions\NotImplementedException,
    nodes\GenericNode,
    nodes\ScalarExpression,
    TreeWalker
};

/**
 * Part of a CASE expression: WHEN Expression THEN Expression
 *
 * @property ScalarExpression $when
 * @property ScalarExpression $then
 */
class WhenExpression extends GenericNode
{
    /** @var ScalarExpression */
    protected $p_when;
    /** @var ScalarExpression */
    protected $p_then;

    public function __construct(ScalarExpression $when, ScalarExpression $then)
    {
        $this->generatePropertyNames();
        $this->setProperty($this->p_when, $when);
        $this->setProperty($this->p_then, $then);
    }

    public function setWhen(ScalarExpression $when): void
    {
        $this->setProperty($this->p_when, $when);
    }

    public function setThen(ScalarExpression $then): void
    {
        $this->setProperty($this->p_then, $then);
    }

    public function dispatch(TreeWalker $walker)
    {
        // handled by dispatch of CaseExpression as this cannot appear outside of CASE
        throw new NotImplementedException('Dispatch for ' . __CLASS__ . ' not implemented');
    }
}
